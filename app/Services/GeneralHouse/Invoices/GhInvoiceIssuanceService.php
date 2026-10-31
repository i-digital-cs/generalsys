<?php

namespace App\Services\GeneralHouse\Invoices;

use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\User;
use App\Services\GeneralHouse\Orders\GhOrderStateService;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GhInvoiceIssuanceService
{
    public function __construct(
        private readonly GhInvoiceCalculationEngine $engine,
        private readonly GhInvoiceNumberService $numbers,
        private readonly GhOrderStateService $orderState,
    ) {}

    public function issue(GhInvoice $invoice, User $actor, ?int $expectedDraftVersion = null): GhInvoice
    {
        return DB::transaction(function () use ($invoice, $actor, $expectedDraftVersion) {
            /** @var GhInvoice $locked */
            $locked = GhInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->isIssued()) {
                // Idempotent: already issued.
                return $locked->fresh(['lines', 'adjustments']);
            }

            if (! $locked->isDraft()) {
                throw ValidationException::withMessages([
                    'invoice' => [__('gh.errors.invoice_not_draft')],
                ]);
            }

            if ($expectedDraftVersion !== null && $expectedDraftVersion !== (int) $locked->draft_version) {
                throw ValidationException::withMessages([
                    'draft_version' => [__('gh.errors.invoice_stale_draft')],
                ]);
            }

            $order = GhOrder::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
            if ($order->status !== GhOrderStatus::WORKING || $order->picked_up_at === null) {
                throw ValidationException::withMessages([
                    'order' => [__('gh.errors.invoice_requires_working')],
                ]);
            }

            $locked->load(['lines']);
            if ($locked->lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'lines' => [__('gh.errors.invoice_lines_required')],
                ]);
            }

            $linesInput = $locked->lines->map(fn ($l) => [
                'offering_id' => $l->offering_id,
                'received_line_id' => $l->received_line_id,
                'quantity' => $l->quantity,
                'quantity_received' => $l->quantity_received,
                'unit_price' => (string) $l->unit_price,
                'tax_class' => $l->tax_class,
                'item_discount_type' => $l->item_discount_type,
                'item_discount_value' => (string) $l->item_discount_value,
                'sort_order' => $l->sort_order,
                'price_source' => $l->price_source,
                'contract_id' => $l->contract_id,
                'product_name_ar' => $l->product_name_ar,
                'product_name_en' => $l->product_name_en,
                'treatment_name_ar' => $l->treatment_name_ar,
                'treatment_name_en' => $l->treatment_name_en,
                'category_name_ar' => $l->category_name_ar,
                'category_name_en' => $l->category_name_en,
                'category_id' => $l->category_id,
                'product_id' => $l->product_id,
                'treatment_id' => $l->treatment_id,
                'notes' => $l->notes,
            ])->all();

            $header = [
                'invoice_discount_type' => $locked->invoice_discount_type,
                'invoice_discount_value' => (string) $locked->invoice_discount_value,
                'min_order_amount' => $locked->min_order_original_amount,
                'min_order_policy' => $locked->min_order_policy,
                'min_order_override_type' => $locked->min_order_override_type,
                'min_order_override_amount' => $locked->min_order_override_type === 'OVERRIDE_AMOUNT'
                    ? $locked->min_order_effective_amount
                    : null,
                'min_order_tax_class' => $locked->min_order_tax_class ?? config('general_house.min_order_surcharge_tax_class'),
                'standard_vat_rate' => (string) $locked->standard_vat_rate,
            ];

            $calc = $this->engine->calculate($linesInput, $header);
            if ($calc['blocks_issuance']) {
                $msgs = [];
                foreach ($calc['block_reasons'] as $reason) {
                    $msgs[] = __('gh.errors.invoice_block_'.$reason);
                }
                throw ValidationException::withMessages([
                    'invoice' => $msgs !== [] ? $msgs : [__('gh.errors.invoice_blocked')],
                ]);
            }

            // Persist recalculated amounts (authoritative at issue time).
            $locked->lines()->delete();
            $locked->adjustments()->delete();
            foreach ($calc['lines'] as $line) {
                $locked->lines()->create([
                    'sort_order' => $line['sort_order'],
                    'received_line_id' => $line['received_line_id'],
                    'offering_id' => $line['offering_id'],
                    'category_id' => $line['category_id'],
                    'product_id' => $line['product_id'],
                    'treatment_id' => $line['treatment_id'],
                    'quantity' => $line['quantity'],
                    'quantity_received' => $line['quantity_received'],
                    'qty_differs_from_received' => $line['qty_differs_from_received'],
                    'unit_price' => $line['unit_price'],
                    'currency' => 'AED',
                    'price_source' => $line['price_source'],
                    'contract_id' => $line['contract_id'],
                    'tax_class' => $line['tax_class'],
                    'tax_rate' => $line['tax_rate'],
                    'item_discount_type' => $line['item_discount_type'],
                    'item_discount_value' => $line['item_discount_value'],
                    'item_discount_amount' => $line['item_discount_amount'],
                    'line_gross' => $line['line_gross'],
                    'line_after_item_discount' => $line['line_after_item_discount'],
                    'allocated_invoice_discount' => $line['allocated_invoice_discount'],
                    'taxable_base' => $line['taxable_base'],
                    'tax_amount' => $line['tax_amount'],
                    'line_total' => $line['line_total'],
                    'product_name_ar' => $line['product_name_ar'],
                    'product_name_en' => $line['product_name_en'],
                    'treatment_name_ar' => $line['treatment_name_ar'],
                    'treatment_name_en' => $line['treatment_name_en'],
                    'category_name_ar' => $line['category_name_ar'],
                    'category_name_en' => $line['category_name_en'],
                    'notes' => $line['notes'],
                ]);
            }
            foreach ($calc['adjustments'] as $adj) {
                $locked->adjustments()->create($adj);
            }

            $min = $calc['min_order'];
            $number = $this->numbers->nextNumber();
            $issuedAt = Carbon::now('UTC');

            $locked->forceFill([
                'number' => $number,
                'status' => GhInvoiceStatus::ISSUED,
                'payment_status' => 'UNPAID',
                'lines_gross' => $calc['lines_gross'],
                'item_discount_total' => $calc['item_discount_total'],
                'invoice_discount_amount' => $calc['invoice_discount_amount'],
                'lines_after_discounts' => $calc['lines_after_discounts'],
                'min_order_adjustment_amount' => $min['adjustment_amount'],
                'min_order_tax_class' => $min['tax_class'],
                'taxable_base' => $calc['taxable_base'],
                'vat_amount' => $calc['vat_amount'],
                'total_payable' => $calc['total_payable'],
                'issued_at' => $issuedAt,
                'issued_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $locked->save();

            $this->orderState->markDeliveryAfterInvoice($order, $actor, [
                'invoice_id' => $locked->id,
                'invoice_number' => $number,
                'issued_at' => $issuedAt,
            ]);

            return $locked->fresh(['lines', 'adjustments', 'order']);
        });
    }
}
