<?php

namespace App\Services\GeneralHouse\Orders;

use App\Models\GeneralHouse\GhCreditNote;
use App\Models\GeneralHouse\GhDebitAdjustment;
use App\Models\GeneralHouse\GhDriverCashCollection;
use App\Models\GeneralHouse\GhFinancialCorrection;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhMinOrderOverride;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhPayment;
use App\Models\GeneralHouse\GhRefund;
use App\Models\User;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhOrderAccess;
use App\Support\GeneralHouse\GhOrderEventType;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin soft deletion of orders that never reached physical pickup.
 *
 * Read-only towards finance: linked invoices, payments, refunds, credit notes and other
 * financial rows are only inspected (to block deletion) — never modified or removed.
 * Sole authority for gh_orders.deleted_at (stamped in UTC through the model cast).
 */
class GhOrderDeletionService
{
    public const REASON_MAX_LENGTH = 500;

    public function __construct(private readonly GhOrderEventService $events) {}

    /** Whether the Delete action may be offered to this actor for this order. */
    public function isDeletable(?User $actor, GhOrder $order): bool
    {
        return GhOrderAccess::mayManage($actor) && $this->blocker($order) === null;
    }

    /** Localized explanation why the order cannot be deleted; null when eligible. */
    public function blocker(GhOrder $order): ?string
    {
        if (! GhOrderStatus::isPrePickup($order->status)) {
            return __('gh.errors.order_delete_status_blocked', [
                'status' => __('gh.order_status.'.$order->status),
            ]);
        }

        if ($order->picked_up_at !== null || $order->pickup_confirmed_by !== null) {
            return __('gh.errors.order_delete_pickup_confirmed');
        }

        $records = $this->financialRecords($order);
        if ($records !== []) {
            return __('gh.errors.order_delete_financial', [
                'records' => implode(__('gh.orders.delete_list_separator'), array_map(
                    fn (string $key) => __('gh.orders.delete_financial.'.$key),
                    $records,
                )),
            ]);
        }

        return null;
    }

    /**
     * Financial record kinds linked to the order (read-only inspection).
     *
     * @return list<string>
     */
    public function financialRecords(GhOrder $order): array
    {
        $invoices = GhInvoice::query()->where('order_id', $order->id)->get(['id', 'status']);
        $invoiceIds = $invoices->pluck('id')->all();
        $found = [];

        if ($order->invoice_issued_at !== null || $invoices->contains('status', GhInvoiceStatus::ISSUED)) {
            $found[] = 'issued_invoice';
        } elseif ($invoices->isNotEmpty()) {
            $found[] = 'invoice';
        }

        $checks = [
            'payment' => fn () => GhPayment::query()->whereIn('invoice_id', $invoiceIds)->exists(),
            'refund' => fn () => GhRefund::query()->whereIn('invoice_id', $invoiceIds)->exists(),
            'credit_note' => fn () => GhCreditNote::query()->where('order_id', $order->id)->exists(),
            'financial_correction' => fn () => GhFinancialCorrection::query()->whereIn('invoice_id', $invoiceIds)->exists(),
            'debit_adjustment' => fn () => GhDebitAdjustment::query()->where('order_id', $order->id)->exists(),
            'driver_cash' => fn () => GhDriverCashCollection::query()->where('order_id', $order->id)->exists(),
            'min_order_override' => fn () => GhMinOrderOverride::query()->where('order_id', $order->id)->exists(),
        ];
        foreach ($checks as $key => $exists) {
            if ($exists()) {
                $found[] = $key;
            }
        }

        return $found;
    }

    public function delete(GhOrder $order, User $actor, string $reason): GhOrder
    {
        if (! GhOrderAccess::mayManage($actor)) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.order_delete_forbidden')],
            ]);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'delete_reason' => [__('gh.errors.order_delete_reason_required')],
            ]);
        }
        if (mb_strlen($reason) > self::REASON_MAX_LENGTH) {
            throw ValidationException::withMessages([
                'delete_reason' => [__('gh.errors.order_delete_reason_too_long')],
            ]);
        }

        return DB::transaction(fn () => $this->softDelete($order, $actor, $reason));
    }

    private function softDelete(GhOrder $order, User $actor, string $reason): GhOrder
    {
        /** @var GhOrder|null $locked */
        $locked = GhOrder::query()->whereKey($order->id)->lockForUpdate()->first();
        if ($locked === null) {
            throw ValidationException::withMessages([
                'order' => [__('gh.errors.order_delete_already_deleted')],
            ]);
        }

        $blocker = $this->blocker($locked);
        if ($blocker !== null) {
            throw ValidationException::withMessages(['order' => [$blocker]]);
        }

        $now = Carbon::now();
        $statusAtDeletion = $locked->status;
        $locked->forceFill([
            'deleted_at' => $now,
            'deleted_by' => $actor->id,
            'delete_reason' => $reason,
            'updated_by' => $actor->id,
        ])->save();

        $this->events->record($locked, GhOrderEventType::ORDER_DELETED, $actor, $statusAtDeletion, null, [
            'reason' => $reason,
            'status_at_deletion' => $statusAtDeletion,
            'reference' => $locked->reference,
        ], $now);

        return $locked;
    }
}
