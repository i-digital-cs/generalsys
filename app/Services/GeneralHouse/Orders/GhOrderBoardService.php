<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\GeneralHouse\GhOrder;
use App\Models\User;
use App\Support\GeneralHouse\GhOrderBoardTab;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Card-board read model for Admin/Manager orders index and Driver workspace.
 *
 * Status → tab mapping (UI only; lifecycle stays in GhOrderStateService):
 *
 * | Tab      | Admin/Manager                                      | Driver                                              |
 * |----------|----------------------------------------------------|-----------------------------------------------------|
 * | Later    | status = LATER                                     | pickup_driver = me AND status = LATER               |
 * | Actions  | attention filter (not a status)                    | attention filter for my assignments                 |
 * | Pickup   | status = PICKUP                                    | actionable pickups (PICKUP + LATER in lead window)  |
 * | Working  | status = WORKING                                   | WORKING where I am pickup or delivery driver (RO)   |
 * | Delivery | status = DELIVERY                                  | delivery_driver = me AND status = DELIVERY          |
 * | Complete | status = COMPLETED                                 | COMPLETED where I was pickup or delivery driver     |
 *
 * Actions (filtered view): overdue PICKUP windows, missing stage driver, or
 * open failed attempts on PICKUP/DELIVERY. Never invents a new status.
 */
final class GhOrderBoardService
{
    public const FALLBACK_AREA_KEY = '_unassigned';

    public const ADMIN_PER_PAGE = 25;

    public const DRIVER_PER_PAGE = 25;

    public function __construct(
        private readonly GhPickupEligibilityService $eligibility,
        private readonly GhDriverWorkspaceService $workspace,
        private readonly GhDriverMovementLogService $movements,
    ) {}

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    public function tabMeta(?User $driver = null): array
    {
        $out = [];
        foreach (GhOrderBoardTab::ALL as $tab) {
            $out[] = [
                'key' => $tab,
                'label' => __('gh.board.tabs.'.$tab),
                'count' => $this->countForTab($tab, $driver),
            ];
        }

        return $out;
    }

    public function countForTab(string $tab, ?User $driver = null): int
    {
        $tab = GhOrderBoardTab::normalize($tab);

        if ($driver !== null) {
            return match ($tab) {
                GhOrderBoardTab::LATER => $this->driverLaterQuery($driver)->count(),
                GhOrderBoardTab::ACTIONS => $this->driverActionsOrders($driver)->count(),
                GhOrderBoardTab::PICKUP => $this->workspace->actionablePickupsQuery($driver)->count(),
                GhOrderBoardTab::WORKING => $this->driverWorkingQuery($driver)->count(),
                GhOrderBoardTab::DELIVERY => $this->workspace->actionableDeliveriesQuery($driver)->count(),
                GhOrderBoardTab::COMPLETE => $this->driverCompleteQuery($driver)->count(),
                default => 0,
            };
        }

        return $this->adminQueryForTab($tab)->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *   tab: string,
     *   tabs: list<array{key: string, label: string, count: int}>,
     *   groups: list<array{key: string, name: string, sort_order: int, count: int, orders: list<array<string, mixed>>}>,
     *   total: int,
     *   role: string,
     *   pager: array{page: int, per_page: int, total: int, last_page: int, from: int, to: int}
     * }
     */
    public function boardForAdmin(array $filters = []): array
    {
        $tab = GhOrderBoardTab::normalize((string) ($filters['tab'] ?? ''));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = self::ADMIN_PER_PAGE;

        $base = $this->adminQueryForTab($tab, $filters);
        $total = (clone $base)->count();
        $pager = $this->pagerMeta($page, $perPage, $total);

        $orders = (clone $base)
            ->with(['customer', 'address.zone', 'zone', 'pickupDriver', 'deliveryDriver', 'invoice'])
            ->forPage($pager['page'], $perPage)
            ->get();

        return [
            'tab' => $tab,
            'tabs' => $this->tabMeta(null),
            'groups' => $this->groupByArea($orders, $tab, role: 'admin'),
            'total' => $total,
            'role' => 'admin',
            'pager' => $pager,
        ];
    }

    /**
     * @return array{
     *   tab: string,
     *   tabs: list<array{key: string, label: string, count: int}>,
     *   groups: list<array{key: string, name: string, sort_order: int, count: int, orders: list<array<string, mixed>>}>,
     *   total: int,
     *   role: string,
     *   sortable: bool,
     *   reorder_url: ?string,
     *   read_only: bool,
     *   pager: array{page: int, per_page: int, total: int, last_page: int, from: int, to: int}
     * }
     */
    public function boardForDriver(User $driver, string $tab, int $page = 1): array
    {
        $tab = GhOrderBoardTab::normalize($tab);
        $page = max(1, $page);
        $perPage = self::DRIVER_PER_PAGE;
        $with = ['customer', 'address.zone', 'zone', 'invoice'];

        if ($tab === GhOrderBoardTab::ACTIONS) {
            $all = $this->driverActionsOrders($driver)->loadMissing($with);
            $total = $all->count();
            $pager = $this->pagerMeta($page, $perPage, $total);
            $orders = $all->slice(($pager['page'] - 1) * $perPage, $perPage)->values();
        } else {
            $query = match ($tab) {
                GhOrderBoardTab::LATER => $this->driverLaterQuery($driver),
                GhOrderBoardTab::PICKUP => $this->workspace->actionablePickupsQuery($driver),
                GhOrderBoardTab::WORKING => $this->driverWorkingQuery($driver),
                GhOrderBoardTab::DELIVERY => $this->workspace->actionableDeliveriesQuery($driver),
                GhOrderBoardTab::COMPLETE => $this->driverCompleteQuery($driver),
                default => GhOrder::query()->whereRaw('1 = 0'),
            };
            $total = (clone $query)->count();
            $pager = $this->pagerMeta($page, $perPage, $total);
            $orders = (clone $query)->with($with)->forPage($pager['page'], $perPage)->get();
        }

        // Reorder UI only when the full actionable list fits on one page — otherwise
        // a page-local drag would corrupt the global sequence (documented limitation).
        $sortable = in_array($tab, [GhOrderBoardTab::PICKUP, GhOrderBoardTab::DELIVERY], true)
            && $pager['last_page'] === 1;
        $reorderUrl = match ($tab) {
            GhOrderBoardTab::PICKUP => route('gh.driver.reorder-pickups'),
            GhOrderBoardTab::DELIVERY => route('gh.driver.reorder-deliveries'),
            default => null,
        };

        return [
            'tab' => $tab,
            'tabs' => $this->tabMeta($driver),
            // Preserve query order (includes gh_driver_list_positions) for pickup/delivery.
            'groups' => $this->groupByArea(
                $orders,
                $tab,
                role: 'driver',
                preserveInputOrder: in_array($tab, [GhOrderBoardTab::PICKUP, GhOrderBoardTab::DELIVERY], true),
            ),
            'total' => $total,
            'role' => 'driver',
            'sortable' => $sortable,
            'reorder_url' => $reorderUrl,
            'read_only' => $tab === GhOrderBoardTab::WORKING || $tab === GhOrderBoardTab::COMPLETE || $tab === GhOrderBoardTab::LATER,
            'pager' => $pager,
        ];
    }

    /**
     * @return array{page: int, per_page: int, total: int, last_page: int, from: int, to: int}
     */
    private function pagerMeta(int $page, int $perPage, int $total): array
    {
        $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $lastPage);
        $from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
        $to = $total === 0 ? 0 : min($total, $page * $perPage);

        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * @param  Collection<int, GhOrder>  $orders
     * @return list<array{key: string, name: string, sort_order: int, count: int, orders: list<array<string, mixed>>}>
     */
    public function groupByArea(Collection $orders, string $tab, string $role, bool $preserveInputOrder = false): array
    {
        // Driver pickup/delivery lists are ordered by gh_driver_list_positions — do not
        // re-sort by schedule or drag persistence will appear to "not stick" after reload.
        $sorted = $preserveInputOrder ? $orders->values() : $this->sortOrdersForTab($orders, $tab);
        $buckets = [];

        foreach ($sorted as $order) {
            $zone = $order->zone ?? $order->address?->zone;
            $key = $zone !== null ? 'z'.$zone->id : self::FALLBACK_AREA_KEY;
            $name = $zone !== null
                ? $zone->localizedName()
                : __('gh.board.unassigned_area');
            $sortOrder = $zone !== null ? (int) $zone->sort_order : PHP_INT_MAX;

            $buckets[$key] ??= [
                'key' => $key,
                'name' => $name,
                'sort_order' => $sortOrder,
                'orders' => [],
            ];
            $buckets[$key]['orders'][] = $order;
        }

        uasort($buckets, function (array $a, array $b) {
            if ($a['sort_order'] !== $b['sort_order']) {
                return $a['sort_order'] <=> $b['sort_order'];
            }

            return strcmp((string) $a['name'], (string) $b['name']);
        });

        $groups = [];
        foreach ($buckets as $bucket) {
            $cards = [];
            $seq = 1;
            foreach ($bucket['orders'] as $order) {
                $cards[] = $this->presentCard($order, $tab, $role, $seq);
                $seq++;
            }
            $groups[] = [
                'key' => $bucket['key'],
                'name' => $bucket['name'],
                'sort_order' => $bucket['sort_order'],
                'count' => count($cards),
                'orders' => $cards,
            ];
        }

        return $groups;
    }

    /**
     * @return array<string, mixed>
     */
    public function presentCard(GhOrder $order, string $tab, string $role, int $sequence): array
    {
        $tz = $this->eligibility->displayTimezone($order->timezone);
        $addr = $order->address;
        $addressLine = collect([$addr?->building, $addr?->street, $addr?->area, $addr?->city])
            ->filter()
            ->implode(', ');
        if ($addressLine === '') {
            $addressLine = (string) ($addr?->label ?? '—');
        }

        $isDeliveryStage = in_array($order->status, [GhOrderStatus::DELIVERY, GhOrderStatus::COMPLETED], true)
            || $tab === GhOrderBoardTab::DELIVERY;
        $scheduled = $isDeliveryStage && $order->invoice_issued_at
            ? $order->invoice_issued_at
            : $order->pickup_starts_at;

        $overdue = $order->status === GhOrderStatus::PICKUP
            && ($order->pickup_ends_at?->isPast() || $order->pickup_starts_at?->isPast());

        $taskType = $tab === GhOrderBoardTab::DELIVERY || $order->status === GhOrderStatus::DELIVERY
            ? 'delivery'
            : 'pickup';

        $href = match (true) {
            $role === 'driver' && $taskType === 'delivery' => route('gh.driver.delivery', $order),
            $role === 'driver' => route('gh.driver.pickup', $order),
            default => route('gh.orders.show', $order),
        };

        $readOnly = $role === 'driver' && in_array($tab, [
            GhOrderBoardTab::WORKING,
            GhOrderBoardTab::COMPLETE,
            GhOrderBoardTab::LATER,
        ], true);

        // Restore original GH-7 driver movement path: board CTA opens pickup/delivery
        // workspace where confirm/fail transitions run (same backend as before V2 board).
        $canExecuteTask = $role === 'driver' && ! $readOnly && in_array($order->status, [
            GhOrderStatus::PICKUP,
            GhOrderStatus::LATER,
            GhOrderStatus::DELIVERY,
        ], true);

        $taskActionLabel = match (true) {
            ! $canExecuteTask => null,
            $taskType === 'delivery' => __('gh.board.execute_delivery'),
            default => __('gh.board.execute_pickup'),
        };

        return [
            'id' => (int) $order->id,
            'reference' => (string) $order->reference,
            'sequence' => $sequence,
            'status' => (string) $order->status,
            'status_label' => __('gh.order_status.'.$order->status),
            'customer_name' => $order->customer?->localizedName() ?? '—',
            'address_line' => $addressLine,
            // Compact board date (d/m) keeps 4-col cells readable; full date in title/tooltip.
            'scheduled_date' => $scheduled?->timezone($tz)->format('d/m') ?? '—',
            'scheduled_date_full' => $scheduled?->timezone($tz)->translatedFormat('d M Y') ?? '—',
            'scheduled_time' => $scheduled?->timezone($tz)->format('H:i') ?? '—',
            'schedule_kind' => $isDeliveryStage ? 'delivery' : 'pickup',
            'order_type' => 'NORMAL',
            'order_type_label' => __('gh.board.order_type.NORMAL'),
            'overdue' => $overdue,
            'href' => $href,
            'task_type' => $taskType,
            'maps_url' => $this->mapsUrl($addressLine, $addr?->latitude, $addr?->longitude),
            'pickup_driver' => $order->pickupDriver?->localizedName(),
            'delivery_driver' => $order->deliveryDriver?->localizedName(),
            'can_edit' => $role === 'admin' && $order->isEditableOperationally(),
            'read_only' => $readOnly,
            'can_execute_task' => $canExecuteTask,
            'task_action_label' => $taskActionLabel,
        ];
    }

    /**
     * @param  Collection<int, GhOrder>  $orders
     * @return Collection<int, GhOrder>
     */
    private function sortOrdersForTab(Collection $orders, string $tab): Collection
    {
        return $orders->sort(function (GhOrder $a, GhOrder $b) use ($tab) {
            $aAt = $this->sortInstant($a, $tab);
            $bAt = $this->sortInstant($b, $tab);
            if ($aAt !== $bAt) {
                return $aAt <=> $bAt;
            }

            return $a->id <=> $b->id;
        })->values();
    }

    private function sortInstant(GhOrder $order, string $tab): int
    {
        $at = match ($tab) {
            GhOrderBoardTab::DELIVERY, GhOrderBoardTab::COMPLETE => $order->invoice_issued_at ?? $order->delivered_at ?? $order->pickup_starts_at,
            default => $order->pickup_starts_at,
        };

        return $at?->getTimestamp() ?? PHP_INT_MAX;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<GhOrder>
     */
    private function adminQueryForTab(string $tab, array $filters = []): Builder
    {
        $q = GhOrder::query()->where('status', '!=', GhOrderStatus::CANCELLED);

        $q = match ($tab) {
            GhOrderBoardTab::LATER => $q->where('status', GhOrderStatus::LATER)->orderBy('pickup_starts_at'),
            GhOrderBoardTab::PICKUP => $q->where('status', GhOrderStatus::PICKUP)->orderBy('pickup_starts_at'),
            GhOrderBoardTab::WORKING => $q->where('status', GhOrderStatus::WORKING)->orderByDesc('picked_up_at'),
            GhOrderBoardTab::DELIVERY => $q->where('status', GhOrderStatus::DELIVERY)->orderByDesc('invoice_issued_at'),
            GhOrderBoardTab::COMPLETE => $q->where('status', GhOrderStatus::COMPLETED)->orderByDesc('delivered_at'),
            GhOrderBoardTab::ACTIONS => $this->applyAdminActionsFilter($q)->orderBy('pickup_starts_at'),
            default => $q->where('status', GhOrderStatus::PICKUP)->orderBy('pickup_starts_at'),
        };

        return $this->applySharedFilters($q, $filters);
    }

    /**
     * @param  Builder<GhOrder>  $q
     * @param  array<string, mixed>  $filters
     * @return Builder<GhOrder>
     */
    private function applySharedFilters(Builder $q, array $filters): Builder
    {
        $ref = trim((string) ($filters['reference'] ?? $filters['q'] ?? ''));
        if ($ref !== '') {
            $like = '%'.$ref.'%';
            $q->where(function (Builder $inner) use ($like) {
                $inner->where('reference', 'like', $like)
                    ->orWhereHas('customer', function ($c) use ($like) {
                        $c->where('name_ar', 'like', $like)
                            ->orWhere('name_en', 'like', $like)
                            ->orWhere('phone', 'like', $like);
                    });
            });
        }

        $zoneId = (int) ($filters['zone_id'] ?? 0);
        if ($zoneId > 0) {
            $q->where('zone_id', $zoneId);
        }

        $pickupDriverId = (int) ($filters['pickup_driver_id'] ?? 0);
        if ($pickupDriverId > 0) {
            $q->where('pickup_driver_id', $pickupDriverId);
        }

        return $q;
    }

    /**
     * @param  Builder<GhOrder>  $q
     * @return Builder<GhOrder>
     */
    private function applyAdminActionsFilter(Builder $q): Builder
    {
        $now = Carbon::now('UTC');

        return $q->where(function (Builder $outer) use ($now) {
            $outer->where(function (Builder $overdue) use ($now) {
                $overdue->where('status', GhOrderStatus::PICKUP)
                    ->where(function (Builder $w) use ($now) {
                        $w->where('pickup_ends_at', '<', $now)
                            ->orWhere('pickup_starts_at', '<', $now);
                    });
            })->orWhere(function (Builder $missingPickup) {
                $missingPickup->where('status', GhOrderStatus::PICKUP)
                    ->whereNull('pickup_driver_id');
            })->orWhere(function (Builder $missingDelivery) {
                $missingDelivery->where('status', GhOrderStatus::DELIVERY)
                    ->whereNull('delivery_driver_id');
            })->orWhereHas('attempts', function (Builder $a) {
                $a->whereIn('attempt_type', ['PICKUP', 'DELIVERY']);
            });
        })->whereIn('status', [GhOrderStatus::PICKUP, GhOrderStatus::DELIVERY, GhOrderStatus::WORKING]);
    }

    /** @return Builder<GhOrder> */
    private function driverLaterQuery(User $driver): Builder
    {
        $now = Carbon::now($this->eligibility->timezone());
        $cutoff = $this->eligibility->toUtcSql($now->copy()->addHours($this->eligibility->leadHours()));

        return GhOrder::query()
            ->where('pickup_driver_id', (int) $driver->id)
            ->where('status', GhOrderStatus::LATER)
            ->where('pickup_starts_at', '>', $cutoff)
            ->orderBy('pickup_starts_at');
    }

    /** @return Builder<GhOrder> */
    private function driverWorkingQuery(User $driver): Builder
    {
        $uid = (int) $driver->id;

        return GhOrder::query()
            ->where('status', GhOrderStatus::WORKING)
            ->where(function (Builder $q) use ($uid) {
                $q->where('pickup_driver_id', $uid)
                    ->orWhere('delivery_driver_id', $uid);
            })
            ->orderByDesc('picked_up_at');
    }

    /** @return Builder<GhOrder> */
    private function driverCompleteQuery(User $driver): Builder
    {
        $uid = (int) $driver->id;

        return GhOrder::query()
            ->where('status', GhOrderStatus::COMPLETED)
            ->where(function (Builder $q) use ($uid) {
                $q->where('pickup_driver_id', $uid)
                    ->orWhere('delivery_driver_id', $uid);
            })
            ->orderByDesc('delivered_at');
    }

    /**
     * @return Collection<int, GhOrder>
     */
    private function driverActionsOrders(User $driver): Collection
    {
        $failed = $this->movements->failedFollowupsForDriver($driver, 50)
            ->map(fn ($m) => $m->order)
            ->filter();

        $overdue = $this->workspace->actionablePickupsQuery($driver)
            ->with(['customer', 'address.zone', 'zone', 'invoice'])
            ->get()
            ->filter(function (GhOrder $order) {
                return $order->status === GhOrderStatus::PICKUP
                    && ($order->pickup_ends_at?->isPast() || $order->pickup_starts_at?->isPast());
            });

        return $failed->concat($overdue)->unique('id')->values();
    }

    private function mapsUrl(string $addressLine, mixed $lat, mixed $lng): ?string
    {
        if ($lat !== null && $lng !== null && $lat !== '' && $lng !== '') {
            return 'https://www.google.com/maps/search/?api=1&query='.urlencode($lat.','.$lng);
        }
        if (trim($addressLine) === '' || $addressLine === '—') {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.urlencode($addressLine);
    }
}
