<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\User;
use App\Services\GeneralHouse\Catalog\GhSellableCatalogService;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-model for the driver workspace. Status changes remain in GhOrderStateService.
 */
class GhDriverWorkspaceService
{
    public const LIST_PICKUP = 'PICKUP';

    public const LIST_DELIVERY = 'DELIVERY';

    public function __construct(
        private readonly GhPickupEligibilityService $eligibility,
        private readonly GhDriverMovementLogService $movements,
    ) {}

    /**
     * Pickups the driver can act on now: PICKUP status (incl. overdue unfinished),
     * or LATER already inside the eligibility lead window (24h by default).
     *
     * @return Builder<GhOrder>
     */
    public function actionablePickupsQuery(User $driver, ?Carbon $now = null): Builder
    {
        $cutoff = $this->eligibility->toUtcSql(($now ?? $this->eligibility->now())->copy()->addHours($this->eligibility->leadHours()));

        return $this->orderedByPosition(
            GhOrder::query()
                ->where('pickup_driver_id', (int) $driver->id)
                ->where(function (Builder $q) use ($cutoff) {
                    $q->where('status', GhOrderStatus::PICKUP)
                        ->orWhere(function (Builder $later) use ($cutoff) {
                            $later->where('status', GhOrderStatus::LATER)->where('pickup_starts_at', '<=', $cutoff);
                        });
                }),
            $driver,
            self::LIST_PICKUP
        )->orderBy('gh_orders.pickup_starts_at');
    }

    /** @return Builder<GhOrder> */
    public function actionableDeliveriesQuery(User $driver): Builder
    {
        return $this->orderedByPosition(
            GhOrder::query()
                ->where('delivery_driver_id', (int) $driver->id)
                ->where('status', GhOrderStatus::DELIVERY),
            $driver,
            self::LIST_DELIVERY
        )->orderByDesc('gh_orders.invoice_issued_at');
    }

    /**
     * @param  Builder<GhOrder>  $query
     * @return Builder<GhOrder>
     */
    private function orderedByPosition(Builder $query, User $driver, string $listType): Builder
    {
        return $query
            ->select('gh_orders.*')
            ->leftJoin('gh_driver_list_positions as dlp', function ($join) use ($driver, $listType) {
                $join->on('dlp.order_id', '=', 'gh_orders.id')
                    ->where('dlp.driver_user_id', '=', (int) $driver->id)
                    ->where('dlp.list_type', '=', $listType);
            })
            ->orderByRaw('dlp.position IS NULL')
            ->orderBy('dlp.position');
    }

    /**
     * @return array{
     *   actionable_pickups: Collection<int, GhOrder>,
     *   upcoming_pickups: Collection<int, GhOrder>,
     *   pending_deliveries: Collection<int, GhOrder>,
     *   completed: Collection<int, GhDriverMovement>,
     *   failed_followups: Collection<int, GhDriverMovement>,
     *   business_date: string,
     *   timezone: string,
     *   lead_hours: int,
     * }
     */
    public function dashboard(User $driver): array
    {
        $tz = (string) config('general_house.timezone', 'Asia/Dubai');
        $now = Carbon::now($tz);
        $uid = (int) $driver->id;

        $with = ['customer', 'address.zone', 'invoice'];

        $actionablePickups = $this->actionablePickupsQuery($driver, $now)->with($with)->limit(50)->get();

        // Informational only: LATER and not yet inside the eligibility window.
        $upcomingPickups = GhOrder::query()
            ->with($with)
            ->where('pickup_driver_id', $uid)
            ->where('status', GhOrderStatus::LATER)
            ->where('pickup_starts_at', '>', $this->eligibility->toUtcSql($now->copy()->addHours($this->eligibility->leadHours())))
            ->orderBy('pickup_starts_at')
            ->limit(50)
            ->get();

        $pendingDeliveries = $this->actionableDeliveriesQuery($driver)->with($with)->limit(50)->get();

        // Separate PICKUP / DELIVERY movement cards — never one combined order row.
        $completed = $this->movements->recentCompletedForDriver($driver, 25);
        $failedFollowups = $this->movements->failedFollowupsForDriver($driver, 50);

        return [
            'actionable_pickups' => $actionablePickups,
            'upcoming_pickups' => $upcomingPickups,
            'pending_deliveries' => $pendingDeliveries,
            'completed' => $completed,
            'failed_followups' => $failedFollowups,
            'business_date' => $now->toDateString(),
            'timezone' => $tz,
            'lead_hours' => $this->eligibility->leadHours(),
        ];
    }

    /**
     * Product → treatments structure for pickers.
     * Prefer {@see staffCountingProductGroups()} for laundry staff (includes products without offerings).
     *
     * @param  Collection<int, array{offering: GhProductOffering}>  $offerings
     * @return list<array{product_id: int, code: string, name_ar: string, name_en: string, image_url: ?string, treatments: list<array{offering_id: int, name_ar: string, name_en: string}>}>
     */
    public function pickupProductGroups(Collection $offerings): array
    {
        $groups = [];
        foreach ($offerings as $row) {
            /** @var GhProductOffering $offering */
            $offering = $row['offering'];
            $product = $offering->product;
            if ($product === null) {
                continue;
            }
            $groups[$product->id] ??= [
                'product_id' => (int) $product->id,
                'code' => (string) ($product->code ?? ''),
                'name_ar' => (string) $product->name_ar,
                'name_en' => (string) ($product->name_en ?: $product->name_ar),
                'image_url' => $product->imageUrl(),
                'treatments' => [],
            ];
            $groups[$product->id]['treatments'][] = [
                'offering_id' => (int) $offering->id,
                'name_ar' => (string) $offering->treatment?->name_ar,
                'name_en' => (string) ($offering->treatment?->name_en ?: $offering->treatment?->name_ar),
            ];
        }

        return array_values($groups);
    }

    /**
     * All active catalog products for staff WORKING counting (thumbnails + search).
     * Products with no active offerings appear with an empty treatments list.
     *
     * @return list<array{product_id: int, code: string, name_ar: string, name_en: string, image_url: ?string, treatments: list<array{offering_id: int, name_ar: string, name_en: string}>}>
     */
    public function staffCountingProductGroups(): array
    {
        $products = app(GhSellableCatalogService::class)
            ->activeProductsForCounting();

        $groups = [];
        foreach ($products as $product) {
            $treatments = [];
            foreach ($product->offerings as $offering) {
                $treatments[] = [
                    'offering_id' => (int) $offering->id,
                    'name_ar' => (string) $offering->treatment?->name_ar,
                    'name_en' => (string) ($offering->treatment?->name_en ?: $offering->treatment?->name_ar),
                ];
            }
            $groups[] = [
                'product_id' => (int) $product->id,
                'code' => (string) ($product->code ?? ''),
                'name_ar' => (string) $product->name_ar,
                'name_en' => (string) ($product->name_en ?: $product->name_ar),
                'image_url' => $product->imageUrl(),
                'treatments' => $treatments,
            ];
        }

        return $groups;
    }

    /** Human-readable reason a pickup cannot be confirmed yet (null when it can). */
    public function pickupUnavailableNotice(GhOrder $order, string $displayTz): ?string
    {
        if ($order->status === GhOrderStatus::PICKUP) {
            return null;
        }
        if ($order->status === GhOrderStatus::LATER) {
            return __('gh.orders.pickup_not_eligible', [
                'hours' => $this->eligibility->leadHours(),
                'time' => $order->pickup_starts_at?->timezone($displayTz)->translatedFormat('Y-m-d H:i') ?? '—',
            ]);
        }
        if ($order->status === GhOrderStatus::WORKING && $order->picked_up_at !== null) {
            return null;
        }

        return __('gh.orders.pickup_wrong_status', ['status' => __('gh.order_status.'.$order->status)]);
    }

    public function assertCanOpenTask(User $driver, GhOrder $order, string $taskType): void
    {
        $uid = (int) $driver->id;
        if ($taskType === 'pickup') {
            if ((int) $order->pickup_driver_id !== $uid) {
                abort(403);
            }

            return;
        }
        if ($taskType === 'delivery') {
            if ((int) $order->delivery_driver_id !== $uid) {
                abort(403);
            }

            return;
        }
        abort(404);
    }
}
