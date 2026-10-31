<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Models\GeneralHouse\GhOrder;
use App\Services\GeneralHouse\Catalog\GhPricingResolver;
use App\Services\GeneralHouse\Orders\GhDriverWorkspaceService;
use App\Services\GeneralHouse\Orders\GhPickupEligibilityService;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Support\GeneralHouse\GhAttemptReason;
use App\Support\GeneralHouse\GhOrderAccess;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverWorkspaceController extends Controller
{
    public function __construct(
        private readonly GhDriverWorkspaceService $workspace,
        private readonly GhPickupEligibilityService $eligibility,
        private readonly GhPricingResolver $pricing,
        private readonly GhPaymentBalanceService $balances,
    ) {}

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        abort_unless(GhOrderAccess::mayUseDriverWorkspace($user), 403);

        $data = $this->workspace->dashboard($user);

        return view('gh.driver.dashboard', array_merge($data, [
            'driver' => $user,
            'displayTz' => $data['timezone'],
        ]));
    }

    public function pickup(Request $request, GhOrder $order): View
    {
        $user = $request->user();
        abort_unless(GhOrderAccess::mayUseDriverWorkspace($user), 403);
        $this->workspace->assertCanOpenTask($user, $order, 'pickup');

        $order->load([
            'customer',
            'address.zone',
            'lines',
            'receivedLines',
            'attempts.actor',
            'events' => fn ($q) => $q->orderByDesc('id')->limit(20),
            'events.actor',
        ]);

        $offerings = collect();
        if ($order->status === GhOrderStatus::PICKUP && $order->customer) {
            $offerings = $this->pricing->availableOfferingsForCustomer($order->customer, $order->pickup_starts_at);
        }

        return view('gh.driver.pickup', [
            'order' => $order,
            'offerings' => $offerings,
            'displayTz' => $this->eligibility->displayTimezone($order->timezone),
            'canConfirm' => GhOrderAccess::mayConfirmPickup($user, $order)
                && $order->status === GhOrderStatus::PICKUP,
            'canFail' => GhOrderAccess::mayRecordPickupAttempt($user, $order)
                && $order->status === GhOrderStatus::PICKUP,
            'failReasons' => GhAttemptReason::PICKUP,
        ]);
    }

    public function delivery(Request $request, GhOrder $order): View
    {
        $user = $request->user();
        abort_unless(GhOrderAccess::mayUseDriverWorkspace($user), 403);
        $this->workspace->assertCanOpenTask($user, $order, 'delivery');

        $order->load([
            'customer',
            'address.zone',
            'lines',
            'receivedLines',
            'attempts.actor',
            'invoice',
            'events' => fn ($q) => $q->orderByDesc('id')->limit(20),
            'events.actor',
        ]);

        $paymentHint = null;
        if ($order->invoice && $order->invoice->isIssued()) {
            $balance = $this->balances->forInvoice($order->invoice);
            $paymentHint = [
                'status' => $balance['payment_status'],
                'remaining' => $balance['remaining'],
                'is_unpaid' => $balance['payment_status'] !== 'PAID',
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
            'awaitingInvoice' => $order->status === GhOrderStatus::WORKING,
        ]);
    }

    public function history(Request $request): View
    {
        $user = $request->user();
        abort_unless(GhOrderAccess::mayUseDriverWorkspace($user), 403);

        $uid = (int) $user->id;
        $rows = GhOrder::query()
            ->with(['customer', 'address'])
            ->where(function ($q) use ($uid) {
                $q->where('pickup_driver_id', $uid)
                    ->orWhere('delivery_driver_id', $uid);
            })
            ->whereIn('status', [GhOrderStatus::COMPLETED, GhOrderStatus::CANCELLED, GhOrderStatus::WORKING])
            ->orderByDesc('updated_at')
            ->paginate(25);

        return view('gh.driver.history', [
            'rows' => $rows,
            'displayTz' => (string) config('general_house.timezone', 'Asia/Dubai'),
        ]);
    }
}
