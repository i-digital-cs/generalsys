<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\CancelGhOrderRequest;
use App\Http\Requests\GeneralHouse\ConfirmGhDeliveryRequest;
use App\Http\Requests\GeneralHouse\ConfirmGhPickupRequest;
use App\Http\Requests\GeneralHouse\ReassignGhDriverRequest;
use App\Http\Requests\GeneralHouse\RecordGhAttemptRequest;
use App\Http\Requests\GeneralHouse\RescheduleGhOrderRequest;
use App\Models\GeneralHouse\GhOrder;
use App\Services\GeneralHouse\Orders\GhOrderAssignmentService;
use App\Services\GeneralHouse\Orders\GhOrderService;
use App\Services\GeneralHouse\Orders\GhOrderStateService;
use App\Support\GeneralHouse\GhAssignmentResponsibility;
use App\Support\GeneralHouse\GhOrderAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrderLogisticsController extends Controller
{
    public function __construct(
        private readonly GhOrderStateService $states,
        private readonly GhOrderService $orders,
        private readonly GhOrderAssignmentService $assignments,
    ) {}

    public function confirmPickup(ConfirmGhPickupRequest $request, GhOrder $order): RedirectResponse
    {
        $this->states->confirmPickup($order, $request->user(), $request->validated());

        return $this->redirectAfterDriverAction($request, $order, 'pickup')
            ->with('success', __('gh.flash.pickup_confirmed'));
    }

    public function confirmDelivery(ConfirmGhDeliveryRequest $request, GhOrder $order): RedirectResponse
    {
        $this->states->confirmDelivery($order, $request->user(), $request->validated());

        return $this->redirectAfterDriverAction($request, $order, 'delivery')
            ->with('success', __('gh.flash.delivery_confirmed'));
    }

    public function cancel(CancelGhOrderRequest $request, GhOrder $order): RedirectResponse
    {
        $this->states->cancel($order, $request->user(), (string) $request->validated('cancel_reason'));

        return redirect()
            ->route('gh.orders.show', $order)
            ->with('success', __('gh.flash.order_cancelled'));
    }

    public function reschedule(RescheduleGhOrderRequest $request, GhOrder $order): RedirectResponse
    {
        $data = $request->validated();
        $window = $this->orders->parsePickupWindow($data);
        if (array_key_exists('scheduling_notes', $data)) {
            $window['scheduling_notes'] = $data['scheduling_notes'];
        }
        $this->states->reschedulePickup($order, $request->user(), $window);

        return redirect()
            ->route('gh.orders.show', $order)
            ->with('success', __('gh.flash.order_rescheduled'));
    }

    public function failedPickup(RecordGhAttemptRequest $request, GhOrder $order): RedirectResponse
    {
        if (! GhOrderAccess::mayRecordPickupAttempt($request->user(), $order)) {
            abort(403);
        }
        $this->states->recordFailedPickup($order, $request->user(), $request->validated());

        return $this->redirectAfterDriverAction($request, $order, 'pickup')
            ->with('success', __('gh.flash.pickup_failed_recorded'));
    }

    public function failedDelivery(RecordGhAttemptRequest $request, GhOrder $order): RedirectResponse
    {
        if (! GhOrderAccess::mayRecordDeliveryAttempt($request->user(), $order)) {
            abort(403);
        }
        $this->states->recordFailedDelivery($order, $request->user(), $request->validated());

        return $this->redirectAfterDriverAction($request, $order, 'delivery')
            ->with('success', __('gh.flash.delivery_failed_recorded'));
    }

    private function redirectAfterDriverAction(Request $request, GhOrder $order, string $taskType): RedirectResponse
    {
        $user = $request->user();
        if ($user !== null && $user->isDriver()) {
            $route = $taskType === 'delivery' ? 'gh.driver.delivery' : 'gh.driver.pickup';

            return redirect()->route($route, $order);
        }

        return redirect()->route('gh.orders.show', $order);
    }

    public function reassignDriver(ReassignGhDriverRequest $request, GhOrder $order): RedirectResponse
    {
        $data = $request->validated();
        $responsibility = $data['responsibility'];
        $driverId = isset($data['driver_user_id']) && $data['driver_user_id'] !== null && $data['driver_user_id'] !== ''
            ? (int) $data['driver_user_id']
            : null;

        if ($responsibility === GhAssignmentResponsibility::PICKUP) {
            if ($driverId === null) {
                return back()->withErrors(['driver_user_id' => __('gh.errors.driver_required')]);
            }
            $this->assignments->assignPickup($order, $driverId, $request->user());
        } else {
            $this->assignments->assignDelivery($order, $driverId, $request->user());
        }

        return redirect()
            ->route('gh.orders.show', $order)
            ->with('success', __('gh.flash.driver_reassigned'));
    }
}
