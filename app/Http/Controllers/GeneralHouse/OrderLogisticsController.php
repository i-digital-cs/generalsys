<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\ApproveGhReceivedPricingRequest;
use App\Http\Requests\GeneralHouse\CancelGhOrderRequest;
use App\Http\Requests\GeneralHouse\ConfirmGhDeliveryRequest;
use App\Http\Requests\GeneralHouse\ConfirmGhPickupRequest;
use App\Http\Requests\GeneralHouse\DeleteGhOrderRequest;
use App\Http\Requests\GeneralHouse\ManualGhOrderStatusRequest;
use App\Http\Requests\GeneralHouse\ReassignGhDriverRequest;
use App\Http\Requests\GeneralHouse\RecordGhAttemptRequest;
use App\Http\Requests\GeneralHouse\RescheduleGhOrderRequest;
use App\Http\Requests\GeneralHouse\ResumeGhAutoPromoteRequest;
use App\Http\Requests\GeneralHouse\SyncGhReceivedLinesRequest;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderReceivedLine;
use App\Services\GeneralHouse\Orders\GhOrderAssignmentService;
use App\Services\GeneralHouse\Orders\GhOrderDeletionService;
use App\Services\GeneralHouse\Orders\GhOrderManualTransitionService;
use App\Services\GeneralHouse\Orders\GhOrderService;
use App\Services\GeneralHouse\Orders\GhOrderStateService;
use App\Services\GeneralHouse\Orders\GhReceivedItemService;
use App\Services\GeneralHouse\Orders\GhReceivedPricingReviewService;
use App\Services\GeneralHouse\Payments\GhDriverDeliveryPaymentService;
use App\Support\GeneralHouse\GhAssignmentResponsibility;
use App\Support\GeneralHouse\GhOrderAccess;
use App\Support\GeneralHouse\GhOrderBoardTab;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderLogisticsController extends Controller
{
    public function __construct(
        private readonly GhOrderStateService $states,
        private readonly GhOrderService $orders,
        private readonly GhOrderAssignmentService $assignments,
        private readonly GhReceivedPricingReviewService $pricingReviews,
        private readonly GhReceivedItemService $receivedItems,
        private readonly GhDriverDeliveryPaymentService $driverPayments,
        private readonly GhOrderManualTransitionService $manualTransitions,
        private readonly GhOrderDeletionService $deletions,
    ) {}

    public function confirmPickup(ConfirmGhPickupRequest $request, GhOrder $order): RedirectResponse
    {
        $this->states->confirmPickup($order, $request->user(), $request->validated());

        return $this->redirectAfterDriverAction($request, $order, 'pickup')
            ->with('success', __('gh.flash.pickup_confirmed'));
    }

    public function confirmDelivery(ConfirmGhDeliveryRequest $request, GhOrder $order): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($order, $request, &$data) {
            $result = $this->driverPayments->handleBeforeDeliveryConfirm($order, $request->user(), $data);
            $data['notes'] = $result['notes'];
            $this->states->confirmDelivery($order, $request->user(), $data);
        });

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

    public function markDeliveryReady(Request $request, GhOrder $order): RedirectResponse
    {
        if (! GhOrderAccess::mayManage($request->user())) {
            abort(403);
        }

        $this->states->markDeliveryReady($order, $request->user());

        return redirect()
            ->route('gh.orders.show', $order)
            ->with('success', __('gh.flash.delivery_ready'));
    }

    public function manualStatus(ManualGhOrderStatusRequest $request, GhOrder $order): RedirectResponse
    {
        $this->manualTransitions->transition($order, $request->user(), $request->validated());

        return redirect()
            ->route('gh.orders.show', $order)
            ->with('success', __('gh.flash.manual_status_updated'));
    }

    public function destroy(DeleteGhOrderRequest $request, GhOrder $order): RedirectResponse
    {
        $this->deletions->delete($order, $request->user(), (string) $request->validated('delete_reason'));

        return redirect()
            ->route('gh.orders.index')
            ->with('success', __('gh.flash.order_deleted', ['reference' => $order->reference]));
    }

    public function resumeAutoPromote(ResumeGhAutoPromoteRequest $request, GhOrder $order): RedirectResponse
    {
        $this->manualTransitions->resumeAutoPromote($order, $request->user(), $request->validated());

        return redirect()
            ->route('gh.orders.show', $order)
            ->with('success', __('gh.flash.auto_promote_resumed'));
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

    /**
     * Drivers return to the workspace list (not the completed task detail).
     * Managers stay on the order detail for follow-up actions.
     */
    private function redirectAfterDriverAction(Request $request, GhOrder $order, string $taskType): RedirectResponse
    {
        $user = $request->user();
        if ($user !== null && $user->isDriver()) {
            $tab = $taskType === 'delivery' ? GhOrderBoardTab::DELIVERY : GhOrderBoardTab::PICKUP;

            return redirect()->route('gh.driver.dashboard', ['tab' => $tab]);
        }

        return redirect()->route('gh.orders.show', $order);
    }

    public function approveReceivedPricing(
        ApproveGhReceivedPricingRequest $request,
        GhOrder $order,
        GhOrderReceivedLine $receivedLine,
    ): RedirectResponse {
        $this->pricingReviews->approve($order, $receivedLine, $request->validated(), $request->user());

        return redirect()
            ->route('gh.orders.show', $order)
            ->with('success', __('gh.flash.pricing_review_approved'));
    }

    public function syncReceivedLines(SyncGhReceivedLinesRequest $request, GhOrder $order): RedirectResponse
    {
        $this->receivedItems->syncForStaff($order, $request->validated('received') ?? []);

        return redirect()
            ->route('gh.orders.show', $order)
            ->with('success', __('gh.flash.received_synced'));
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
