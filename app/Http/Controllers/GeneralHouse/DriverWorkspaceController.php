<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\ReorderGhDriverListRequest;
use App\Models\GeneralHouse\GhOrder;
use App\Services\GeneralHouse\Orders\GhDriverListOrderService;
use App\Services\GeneralHouse\Orders\GhDriverMovementLogService;
use App\Services\GeneralHouse\Orders\GhDriverWorkspaceService;
use App\Services\GeneralHouse\Orders\GhOrderBoardService;
use App\Services\GeneralHouse\Orders\GhOrderStateService;
use App\Services\GeneralHouse\Orders\GhPickupEligibilityService;
use App\Services\GeneralHouse\Payments\GhDriverDeliveryPaymentService;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Support\GeneralHouse\GhAttemptReason;
use App\Support\GeneralHouse\GhDriverMovementStatus;
use App\Support\GeneralHouse\GhDriverMovementType;
use App\Support\GeneralHouse\GhLaunchConfig;
use App\Support\GeneralHouse\GhOrderAccess;
use App\Support\GeneralHouse\GhOrderBoardTab;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverWorkspaceController extends Controller
{
    public function __construct(
        private readonly GhDriverWorkspaceService $workspace,
        private readonly GhPickupEligibilityService $eligibility,
        private readonly GhPaymentBalanceService $balances,
        private readonly GhDriverDeliveryPaymentService $driverPayments,
        private readonly GhOrderStateService $states,
        private readonly GhDriverListOrderService $listOrder,
        private readonly GhDriverMovementLogService $movements,
        private readonly GhOrderBoardService $board,
    ) {}

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(GhOrderAccess::mayUseDriverWorkspace($user), 403);

        $tab = GhOrderBoardTab::normalize((string) $request->query('tab', GhOrderBoardTab::PICKUP));
        $page = max(1, (int) $request->query('page', 1));
        $tz = (string) config('general_house.timezone', 'Asia/Dubai');

        return view('gh.driver.dashboard', [
            'driver' => $user,
            'board' => $this->board->boardForDriver($user, $tab, $page),
            'business_date' => now($tz)->toDateString(),
            'timezone' => $tz,
            'displayTz' => $tz,
        ]);
    }

    public function pickup(Request $request, GhOrder $order): View
    {
        $user = $request->user();
        abort_unless(GhOrderAccess::mayUseDriverWorkspace($user), 403);
        $this->workspace->assertCanOpenTask($user, $order, 'pickup');

        if ($order->status === GhOrderStatus::LATER) {
            // Promotes to PICKUP only when inside the eligibility window; otherwise a no-op.
            $order = $this->states->reevaluatePrePickupStatus($order, $user);
        }

        $order->load([
            'customer',
            'address.zone',
            'attempts.actor',
            'invoice',
            'events' => fn ($q) => $q->orderByDesc('id')->limit(20),
            'events.actor',
        ]);

        $displayTz = $this->eligibility->displayTimezone($order->timezone);
        $isPickup = $order->status === GhOrderStatus::PICKUP;

        return view('gh.driver.pickup', [
            'order' => $order,
            'notYetEligible' => $this->workspace->pickupUnavailableNotice($order, $displayTz),
            'displayTz' => $displayTz,
            'canConfirm' => GhOrderAccess::mayConfirmPickup($user, $order) && $isPickup,
            'canFail' => GhOrderAccess::mayRecordPickupAttempt($user, $order) && $isPickup,
            'failReasons' => GhAttemptReason::PICKUP,
            'expectedPaymentMethod' => null,
        ]);
    }

    public function reorderPickups(ReorderGhDriverListRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->listOrder->reorder(
            $request->user(),
            GhDriverWorkspaceService::LIST_PICKUP,
            $data['order_ids'],
            $data['previous_order_ids'] ?? null,
        );

        return response()->json(['ok' => true]);
    }

    public function reorderDeliveries(ReorderGhDriverListRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->listOrder->reorder(
            $request->user(),
            GhDriverWorkspaceService::LIST_DELIVERY,
            $data['order_ids'],
            $data['previous_order_ids'] ?? null,
        );

        return response()->json(['ok' => true]);
    }

    public function delivery(Request $request, GhOrder $order): View
    {
        $user = $request->user();
        abort_unless(GhOrderAccess::mayUseDriverWorkspace($user), 403);
        $this->workspace->assertCanOpenTask($user, $order, 'delivery');

        $order->load([
            'customer',
            'address.zone',
            'attempts.actor',
            'invoice',
            'events' => fn ($q) => $q->orderByDesc('id')->limit(20),
            'events.actor',
        ]);

        $paymentHint = null;
        if ($order->invoice && $order->invoice->isIssued()) {
            $ctx = $this->driverPayments->deliveryPaymentContext($order->invoice);
            $paymentHint = [
                'status' => $ctx['payment_status'],
                'remaining' => $ctx['remaining'],
                'invoice_total' => $ctx['invoice_total'],
                'is_unpaid' => $ctx['needs_method'],
            ];
        }

        return view('gh.driver.delivery', [
            'order' => $order,
            'displayTz' => $this->eligibility->displayTimezone($order->timezone),
            'canConfirm' => GhOrderAccess::mayConfirmDelivery($user, $order)
                && $order->status === GhOrderStatus::DELIVERY,
            'canFail' => GhOrderAccess::mayRecordDeliveryAttempt($user, $order)
                && $order->status === GhOrderStatus::DELIVERY,
            'failReasons' => GhAttemptReason::DELIVERY,
            'paymentHint' => $paymentHint,
            'deliveryPaymentEnabled' => GhLaunchConfig::driverDeliveryPaymentEnabled(),
            'awaitingInvoice' => $order->status === GhOrderStatus::WORKING,
        ]);
    }

    public function history(Request $request): View
    {
        $user = $request->user();
        abort_unless(GhOrderAccess::mayUseDriverWorkspace($user), 403);

        $filters = [
            'q' => $request->string('q')->toString() ?: null,
            'type' => $request->string('type')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'date_from' => $request->string('date_from')->toString() ?: null,
            'date_to' => $request->string('date_to')->toString() ?: null,
        ];

        $rows = $this->movements->paginateForDriver($user, $filters);

        return view('gh.driver.history', [
            'rows' => $rows,
            'filters' => $filters,
            'movementTypes' => GhDriverMovementType::ALL,
            'movementStatuses' => GhDriverMovementStatus::ALL,
            'displayTz' => (string) config('general_house.timezone', 'Asia/Dubai'),
            'hasActiveSearch' => filled($filters['q'] ?? null),
        ]);
    }
}
