<?php

namespace App\Services\GeneralHouse\Invoices;

use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhInvoiceAdjustment;
use App\Models\GeneralHouse\GhInvoiceLine;
use App\Models\GeneralHouse\GhMinOrderOverride;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderReceivedLine;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\User;
use App\Services\GeneralHouse\Catalog\GhPricingResolver;
use App\Services\GeneralHouse\Settings\GhVatConfigService;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhDiscountType;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhLaunchConfig;
use App\Support\GeneralHouse\GhMinOrderOverrideType;
use App\Support\GeneralHouse\GhMoney;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhTaxClass;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GhInvoiceService
{
    public function __construct(
        private readonly GhInvoiceCalculationEngine $engine,
        private readonly GhPricingResolver $pricing,
        private readonly GhVatConfigService $vatConfig,
    ) {}

    public function findPrimaryForOrder(GhOrder $order): ?GhInvoice
    {
        return GhInvoice::query()->where('order_id', $order->id)->first();
    }

    /**
     * Open DRAFT for a WORKING order. Prefills from legacy received lines when present;
     * otherwise creates an empty draft for direct entry in the invoice builder.
     */
    public function createDraftFromOrder(GhOrder $order, User $actor, array $options = []): GhInvoice
    {
        return DB::transaction(function () use ($order, $actor, $options) {
            $locked = GhOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== GhOrderStatus::WORKING || $locked->picked_up_at === null) {
                throw ValidationException::withMessages([
                    'order' => [__('gh.errors.invoice_requires_working')],
                ]);
            }

            if (GhInvoice::query()->where('order_id', $locked->id)->exists()) {
                throw ValidationException::withMessages([
                    'order' => [__('gh.errors.invoice_already_exists')],
                ]);
            }

            $locked->loadMissing(['customer', 'zone', 'contract', 'address']);
            $header = $this->headerFromOrder($locked, $options);

            $received = GhOrderReceivedLine::query()
                ->where('order_id', $locked->id)
                ->where('quantity', '>', 0)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            if ($received->isEmpty()) {
                return $this->persistEmptyDraft($locked, $actor, $header);
            }

            $pendingReview = $received->where('pricing_review_required', true)->values();
            if ($pendingReview->isNotEmpty()) {
                // Prefill is skipped for unresolved OOC received lines — open empty draft instead.
                return $this->persistEmptyDraft($locked, $actor, $header);
            }

            $linesInput = [];
            foreach ($received as $i => $row) {
                $offering = GhProductOffering::query()->with(['product.category', 'treatment'])->find($row->offering_id);
                if ($offering === null) {
                    continue;
                }
                if ($row->staff_price_approved) {
                    $resolved = [
                        'unit_price' => (string) $row->unit_price_estimate,
                        'source' => 'STAFF',
                    ];
                } else {
                    $resolved = $this->pricing->resolvePrice($locked->customer, $offering, $locked->pickup_starts_at);
                }
                $linesInput[] = [
                    'offering_id' => $offering->id,
                    'received_line_id' => $row->id,
                    'quantity' => (int) $row->quantity,
                    'quantity_received' => (int) $row->quantity,
                    'unit_price' => $resolved['unit_price'],
                    'price_source' => $resolved['source'],
                    'contract_id' => $resolved['source'] === 'CONTRACT' ? $locked->contract_id : null,
                    'tax_class' => $offering->tax_class ?: GhTaxClass::STANDARD_RATED,
                    'item_discount_type' => GhDiscountType::NONE,
                    'item_discount_value' => '0',
                    'sort_order' => $i,
                    'product_name_ar' => $row->product_name_ar,
                    'product_name_en' => $row->product_name_en,
                    'treatment_name_ar' => $row->treatment_name_ar,
                    'treatment_name_en' => $row->treatment_name_en,
                    'category_name_ar' => $row->category_name_ar,
                    'category_name_en' => $row->category_name_en,
                    'category_id' => $offering->product?->category_id,
                    'product_id' => $offering->product_id,
                    'treatment_id' => $offering->treatment_id,
                    'pricing_review_required' => false,
                    'staff_price_approved' => (bool) $row->staff_price_approved,
                    'notes' => $row->notes,
                ];
            }

            if ($linesInput === []) {
                return $this->persistEmptyDraft($locked, $actor, $header);
            }

            $calc = $this->engine->calculate($linesInput, $header);

            return $this->persistDraft($locked, $actor, $calc, $header, $linesInput);
        });
    }

    /**
     * Replace draft lines + discounts and recalculate.
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateDraft(GhInvoice $invoice, User $actor, array $payload): GhInvoice
    {
        return DB::transaction(function () use ($invoice, $actor, $payload) {
            /** @var GhInvoice $locked */
            $locked = GhInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isDraft()) {
                throw ValidationException::withMessages([
                    'invoice' => [__('gh.errors.invoice_immutable')],
                ]);
            }

            $expectedVersion = (int) ($payload['draft_version'] ?? $locked->draft_version);
            if ($expectedVersion !== (int) $locked->draft_version) {
                throw ValidationException::withMessages([
                    'draft_version' => [__('gh.errors.invoice_stale_draft')],
                ]);
            }

            $order = GhOrder::query()->whereKey($locked->order_id)->lockForUpdate()->firstOrFail();
            $order->loadMissing(['customer', 'zone', 'contract']);

            $payload = $this->normalizeMinOrderPayload($order, $actor, $payload, $locked);
            // Existing drafts keep their stamped VAT rate — global setting changes do not rewrite them.
            $payload['standard_vat_rate'] = (string) $locked->standard_vat_rate;

            $linesInput = [];
            foreach (array_values($payload['lines'] ?? []) as $i => $row) {
                $linesInput[] = $this->resolveDraftLineInput($order, $row, $i, $actor);
            }

            $header = $this->headerFromOrder($order, $payload);

            $locked->lines()->delete();
            $locked->adjustments()->delete();
            $locked->minOrderOverride()->delete();

            if ($linesInput === []) {
                $this->applyEmptyTotals($locked, $header, $actor, bumpVersion: true);
                $this->writeMinOverride($locked, $order, [
                    'min_order' => [
                        'override_type' => $header['min_order_override_type'] ?? GhMinOrderOverrideType::APPLY_DEFAULT,
                        'original_minimum' => $header['min_order_amount'] ?? '0.00',
                        'effective_minimum' => ($header['min_order_override_type'] ?? '') === GhMinOrderOverrideType::OVERRIDE_AMOUNT
                            ? ($header['min_order_override_amount'] ?? '0.00')
                            : ($header['min_order_amount'] ?? '0.00'),
                        'adjustment_amount' => '0.00',
                    ],
                ], $actor, $payload);

                return $locked->fresh(['lines', 'adjustments', 'minOrderOverride']);
            }

            $calc = $this->engine->calculate($linesInput, $header);
            $this->applyCalcToInvoice($locked, $calc, $header, $actor, bumpVersion: true);
            $this->writeLinesAndAdjustments($locked, $calc);
            $this->writeMinOverride($locked, $order, $calc, $actor, $payload);

            return $locked->fresh(['lines', 'adjustments', 'minOrderOverride']);
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function resolveDraftLineInput(GhOrder $order, array $row, int $i, User $actor): array
    {
        $offeringId = (int) ($row['offering_id'] ?? 0);
        $offering = GhProductOffering::query()->with(['product.category', 'treatment'])->find($offeringId);
        if ($offering === null) {
            throw ValidationException::withMessages([
                "lines.$i.offering_id" => [__('gh.errors.offering_missing')],
            ]);
        }

        $qty = (int) ($row['quantity'] ?? 0);
        if ($qty < 1) {
            throw ValidationException::withMessages([
                "lines.$i.quantity" => [__('gh.errors.invoice_line_qty_invalid')],
            ]);
        }

        $outOfContract = filter_var($row['out_of_contract'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $product = $offering->product;
        $treatment = $offering->treatment;
        $category = $product?->category;
        $qtyReceived = isset($row['quantity_received']) ? (int) $row['quantity_received'] : null;
        $notes = $row['notes'] ?? null;

        try {
            $resolved = $this->pricing->resolvePrice($order->customer, $offering, $order->pickup_starts_at);
            if ($outOfContract && $order->customer?->type === GhCustomerType::B2B) {
                // Explicit OOC even if somehow on contract — still treat as staff-priced exception.
                return $this->staffApprovedOocLine($order, $offering, $row, $i, $actor, $qty, $qtyReceived, $notes);
            }

            return [
                'offering_id' => $offering->id,
                'received_line_id' => $row['received_line_id'] ?? null,
                'quantity' => $qty,
                'quantity_received' => $qtyReceived,
                'unit_price' => $resolved['unit_price'],
                'price_source' => $resolved['source'],
                'contract_id' => $resolved['source'] === 'CONTRACT' ? $order->contract_id : null,
                'tax_class' => $offering->tax_class ?: GhTaxClass::STANDARD_RATED,
                'item_discount_type' => (string) ($row['item_discount_type'] ?? GhDiscountType::NONE),
                'item_discount_value' => $row['item_discount_value'] ?? '0',
                'sort_order' => $i,
                'product_name_ar' => $product?->name_ar ?? '',
                'product_name_en' => $product?->name_en,
                'treatment_name_ar' => $treatment?->name_ar ?? '',
                'treatment_name_en' => $treatment?->name_en,
                'category_name_ar' => $category?->name_ar,
                'category_name_en' => $category?->name_en,
                'category_id' => $category?->id,
                'product_id' => $product?->id,
                'treatment_id' => $treatment?->id,
                'notes' => $notes,
                'pricing_review_required' => false,
                'staff_price_approved' => false,
            ];
        } catch (ValidationException $e) {
            if ($order->customer?->type !== GhCustomerType::B2B || ! $outOfContract) {
                throw $e;
            }

            return $this->staffApprovedOocLine($order, $offering, $row, $i, $actor, $qty, $qtyReceived, $notes);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function staffApprovedOocLine(
        GhOrder $order,
        GhProductOffering $offering,
        array $row,
        int $i,
        User $actor,
        int $qty,
        ?int $qtyReceived,
        mixed $notes,
    ): array {
        $product = $offering->product;
        $treatment = $offering->treatment;
        $category = $product?->category;
        $rawPrice = $row['unit_price'] ?? $row['approved_unit_price'] ?? null;
        $approve = filter_var($row['approve_price'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || (isset($rawPrice) && $rawPrice !== '' && $rawPrice !== null);

        $pending = ! $approve;
        $unit = '0.00';
        $approved = false;
        if ($approve) {
            $unit = GhMoney::of((string) $rawPrice);
            if (bccomp($unit, '0.00', 2) < 0) {
                throw ValidationException::withMessages([
                    "lines.$i.unit_price" => [__('gh.errors.unit_price_invalid')],
                ]);
            }
            $approved = true;
            $pending = false;
            $flag = __('gh.invoices.ooc_approved_note', ['actor' => $actor->localizedName()]);
            $notes = $notes ? $flag.' — '.$notes : $flag;
        } else {
            $flag = __('gh.invoices.ooc_pending_note');
            $notes = $notes ? $flag.' — '.$notes : $flag;
        }

        return [
            'offering_id' => $offering->id,
            'received_line_id' => $row['received_line_id'] ?? null,
            'quantity' => $qty,
            'quantity_received' => $qtyReceived,
            'unit_price' => $unit,
            'price_source' => 'STAFF',
            'contract_id' => null,
            'tax_class' => $offering->tax_class ?: GhTaxClass::STANDARD_RATED,
            'item_discount_type' => (string) ($row['item_discount_type'] ?? GhDiscountType::NONE),
            'item_discount_value' => $row['item_discount_value'] ?? '0',
            'sort_order' => $i,
            'product_name_ar' => $product?->name_ar ?? '',
            'product_name_en' => $product?->name_en,
            'treatment_name_ar' => $treatment?->name_ar ?? '',
            'treatment_name_en' => $treatment?->name_en,
            'category_name_ar' => $category?->name_ar,
            'category_name_en' => $category?->name_en,
            'category_id' => $category?->id,
            'product_id' => $product?->id,
            'treatment_id' => $treatment?->id,
            'notes' => $notes,
            'pricing_review_required' => $pending,
            'staff_price_approved' => $approved,
        ];
    }

    public function preview(array $lines, array $header): array
    {
        return $this->engine->calculate($lines, $header);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function headerFromOrder(GhOrder $order, array $options): array
    {
        $zone = $order->zone;

        return [
            'invoice_discount_type' => (string) ($options['invoice_discount_type'] ?? GhDiscountType::NONE),
            'invoice_discount_value' => $options['invoice_discount_value'] ?? '0',
            'min_order_amount' => $zone?->min_order_amount,
            'min_order_policy' => $zone?->min_order_policy ?? GhLaunchConfig::defaultMinOrderPolicy(),
            'min_order_override_type' => (string) ($options['min_order_override_type'] ?? GhMinOrderOverrideType::APPLY_DEFAULT),
            'min_order_override_amount' => $options['min_order_override_amount'] ?? null,
            'min_order_tax_class' => $options['min_order_tax_class'] ?? config('general_house.min_order_surcharge_tax_class'),
            'standard_vat_rate' => $options['standard_vat_rate'] ?? $this->vatConfig->standardRate(),
            'min_order_reason' => $options['min_order_reason'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function persistEmptyDraft(GhOrder $order, User $actor, array $header): GhInvoice
    {
        $invoice = $this->newDraftHeader($order, $actor);
        $this->applyEmptyTotals($invoice, $header, $actor, bumpVersion: false);

        return $invoice->fresh(['lines', 'adjustments', 'minOrderOverride']);
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function applyEmptyTotals(GhInvoice $invoice, array $header, User $actor, bool $bumpVersion): void
    {
        $invoice->forceFill([
            'lines_gross' => '0.00',
            'item_discount_total' => '0.00',
            'invoice_discount_type' => $header['invoice_discount_type'] ?? GhDiscountType::NONE,
            'invoice_discount_value' => $header['invoice_discount_value'] ?? '0.00',
            'invoice_discount_amount' => '0.00',
            'lines_after_discounts' => '0.00',
            'min_order_original_amount' => $header['min_order_amount'] ?? '0.00',
            'min_order_effective_amount' => (($header['min_order_override_type'] ?? '') === GhMinOrderOverrideType::OVERRIDE_AMOUNT
                || ($header['min_order_override_type'] ?? '') === GhMinOrderOverrideType::WAIVE)
                ? ($header['min_order_override_amount'] ?? '0.00')
                : ($header['min_order_amount'] ?? '0.00'),
            'min_order_policy' => $header['min_order_policy'] ?? GhLaunchConfig::defaultMinOrderPolicy(),
            'min_order_override_type' => $header['min_order_override_type'] ?? GhMinOrderOverrideType::APPLY_DEFAULT,
            'min_order_adjustment_amount' => '0.00',
            'min_order_tax_class' => $header['min_order_tax_class'] ?? null,
            'taxable_base' => '0.00',
            'vat_amount' => '0.00',
            'total_payable' => '0.00',
            'standard_vat_rate' => $header['standard_vat_rate'] ?? $this->vatConfig->standardRate(),
            'updated_by' => $actor->id,
        ]);
        if ($bumpVersion) {
            $invoice->draft_version = (int) $invoice->draft_version + 1;
        }
        $invoice->save();
    }

    private function newDraftHeader(GhOrder $order, User $actor): GhInvoice
    {
        $customer = $order->customer;
        $zone = $order->zone;
        $contract = $order->contract;

        return GhInvoice::query()->forceCreate([
            'order_id' => $order->id,
            'number' => null,
            'status' => GhInvoiceStatus::DRAFT,
            'payment_status' => 'UNPAID',
            'currency' => 'AED',
            'customer_id' => $customer->id,
            'customer_type' => $customer->type,
            'customer_name_ar' => $customer->name_ar,
            'customer_name_en' => $customer->name_en,
            'customer_phone' => $customer->phone,
            'billing_name' => $customer->billing_name,
            'billing_address' => $customer->billing_address,
            'tax_registration_number' => $customer->tax_registration_number,
            'order_reference' => $order->reference,
            'zone_id' => $zone?->id,
            'zone_name_ar' => $zone?->name_ar,
            'zone_name_en' => $zone?->name_en,
            'contract_id' => $contract?->id,
            'contract_reference' => $contract?->reference,
            'draft_version' => 1,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $calc
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $linesInput
     */
    private function persistDraft(GhOrder $order, User $actor, array $calc, array $header, array $linesInput): GhInvoice
    {
        $invoice = $this->newDraftHeader($order, $actor);
        $this->applyCalcToInvoice($invoice, $calc, $header, $actor, bumpVersion: false);
        $this->writeLinesAndAdjustments($invoice, $calc);
        $this->writeMinOverride($invoice, $order, $calc, $actor, $header);

        return $invoice->fresh(['lines', 'adjustments', 'minOrderOverride']);
    }

    /**
     * @param  array<string, mixed>  $calc
     * @param  array<string, mixed>  $header
     */
    private function applyCalcToInvoice(GhInvoice $invoice, array $calc, array $header, User $actor, bool $bumpVersion): void
    {
        $min = $calc['min_order'];
        $invoice->forceFill([
            'lines_gross' => $calc['lines_gross'],
            'item_discount_total' => $calc['item_discount_total'],
            'invoice_discount_type' => $calc['invoice_discount_type'],
            'invoice_discount_value' => $calc['invoice_discount_value'],
            'invoice_discount_amount' => $calc['invoice_discount_amount'],
            'lines_after_discounts' => $calc['lines_after_discounts'],
            'min_order_original_amount' => $min['original_minimum'],
            'min_order_effective_amount' => $min['effective_minimum'],
            'min_order_policy' => $min['policy'],
            'min_order_override_type' => $min['override_type'],
            'min_order_adjustment_amount' => $min['adjustment_amount'],
            'min_order_tax_class' => $min['tax_class'],
            'taxable_base' => $calc['taxable_base'],
            'vat_amount' => $calc['vat_amount'],
            'total_payable' => $calc['total_payable'],
            'standard_vat_rate' => $calc['standard_vat_rate'],
            'updated_by' => $actor->id,
        ]);
        if ($bumpVersion) {
            $invoice->draft_version = (int) $invoice->draft_version + 1;
        }
        $invoice->save();
    }

    /** @param  array<string, mixed>  $calc */
    private function writeLinesAndAdjustments(GhInvoice $invoice, array $calc): void
    {
        foreach ($calc['lines'] as $line) {
            GhInvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
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
                'pricing_review_required' => (bool) ($line['pricing_review_required'] ?? false),
                'staff_price_approved' => (bool) ($line['staff_price_approved'] ?? false),
            ]);
        }

        foreach ($calc['adjustments'] as $adj) {
            GhInvoiceAdjustment::query()->create(array_merge($adj, [
                'invoice_id' => $invoice->id,
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $calc
     * @param  array<string, mixed>  $payload
     */
    /**
     * Zone default is inherited; managers may set any effective minimum >= 0 for this invoice only.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeMinOrderPayload(GhOrder $order, User $actor, array $payload, GhInvoice $invoice): array
    {
        $zoneMin = GhMoney::of((string) ($order->zone?->min_order_amount ?? $invoice->min_order_original_amount ?? '0.00'));

        $hasSubmittedAmount = array_key_exists('min_order_effective_amount', $payload)
            || array_key_exists('min_order_override_amount', $payload);

        if (! $hasSubmittedAmount) {
            // Preserve prior invoice override when the field was not posted (e.g. non-manager path).
            if ($invoice->min_order_override_type === GhMinOrderOverrideType::OVERRIDE_AMOUNT
                || $invoice->min_order_override_type === GhMinOrderOverrideType::WAIVE) {
                $payload['min_order_override_type'] = GhMinOrderOverrideType::OVERRIDE_AMOUNT;
                $payload['min_order_override_amount'] = GhMoney::of((string) $invoice->min_order_effective_amount);
            } else {
                $payload['min_order_override_type'] = GhMinOrderOverrideType::APPLY_DEFAULT;
                $payload['min_order_override_amount'] = null;
            }

            return $payload;
        }

        $raw = $payload['min_order_effective_amount'] ?? $payload['min_order_override_amount'];
        if ($raw === null || $raw === '') {
            throw ValidationException::withMessages([
                'min_order_effective_amount' => [__('gh.errors.min_order_override_amount_required')],
            ]);
        }

        $effective = GhMoney::of((string) $raw);
        if (GhMoney::cmp($effective, '0') < 0) {
            throw ValidationException::withMessages([
                'min_order_effective_amount' => [__('gh.errors.min_order_amount_invalid')],
            ]);
        }

        $changed = GhMoney::cmp($effective, $zoneMin) !== 0;
        if ($changed && ! $actor->isManager()) {
            throw ValidationException::withMessages([
                'min_order_effective_amount' => [__('gh.errors.min_order_override_forbidden')],
            ]);
        }

        if (! $changed) {
            $payload['min_order_override_type'] = GhMinOrderOverrideType::APPLY_DEFAULT;
            $payload['min_order_override_amount'] = null;

            return $payload;
        }

        $reason = trim((string) ($payload['min_order_reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'min_order_reason' => [__('gh.errors.min_order_reason_required')],
            ]);
        }

        $payload['min_order_override_type'] = GhMinOrderOverrideType::OVERRIDE_AMOUNT;
        $payload['min_order_override_amount'] = $effective;
        $payload['min_order_reason'] = $reason;

        return $payload;
    }

    private function writeMinOverride(GhInvoice $invoice, GhOrder $order, array $calc, User $actor, array $payload): void
    {
        $min = $calc['min_order'];
        $overrideType = (string) ($min['override_type'] ?? GhMinOrderOverrideType::APPLY_DEFAULT);

        if ($overrideType === GhMinOrderOverrideType::APPLY_DEFAULT
            && GhMoney::cmp($min['adjustment_amount'] ?? '0', '0') === 0) {
            return;
        }

        if ($overrideType !== GhMinOrderOverrideType::APPLY_DEFAULT && ! $actor->isManager()) {
            throw ValidationException::withMessages([
                'min_order_override_type' => [__('gh.errors.min_order_override_forbidden')],
            ]);
        }

        $reason = trim((string) ($payload['min_order_reason'] ?? ''));
        if ($overrideType !== GhMinOrderOverrideType::APPLY_DEFAULT && $reason === '') {
            throw ValidationException::withMessages([
                'min_order_reason' => [__('gh.errors.min_order_reason_required')],
            ]);
        }

        if ($reason === '' && GhMoney::cmp($min['adjustment_amount'] ?? '0', '0') === 0) {
            return;
        }

        GhMinOrderOverride::query()->create([
            'invoice_id' => $invoice->id,
            'order_id' => $order->id,
            'override_type' => $overrideType,
            'original_minimum' => $min['original_minimum'] ?? '0',
            'effective_minimum' => $min['effective_minimum'] ?? '0',
            'adjustment_amount' => $min['adjustment_amount'],
            'reason' => $reason !== '' ? $reason : 'SYSTEM_SURCHARGE',
            'actor_id' => $actor->id,
            'recorded_at' => Carbon::now('UTC'),
        ]);
    }
}
