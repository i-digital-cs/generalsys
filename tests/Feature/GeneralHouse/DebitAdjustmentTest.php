<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhDebitAdjustment;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderLine;
use App\Models\GeneralHouse\GhOrderReceivedLine;
use App\Models\GeneralHouse\GhPayment;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Corrections\GhCreditNoteService;
use App\Services\GeneralHouse\Corrections\GhDebitAdjustmentService;
use App\Services\GeneralHouse\Invoices\GhInvoiceIssuanceService;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Services\GeneralHouse\Payments\GhPaymentService;
use App\Support\GeneralHouse\GhCreditNoteReason;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhDebitAdjustmentLineType;
use App\Support\GeneralHouse\GhDebitAdjustmentReason;
use App\Support\GeneralHouse\GhDebitAdjustmentStatus;
use App\Support\GeneralHouse\GhInvoicePaymentStatus;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhReceivedDiscrepancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DebitAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['orders', 'invoices', 'payments', 'credit_notes', 'refunds', 'financial_corrections', 'debit_adjustments'] as $seq) {
            if (! DB::table('gh_sequences')->where('name', $seq)->exists()) {
                DB::table('gh_sequences')->insert([
                    'name' => $seq,
                    'next_value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        config([
            'general_house.min_order_surcharge_tax_class' => null,
            'general_house.min_order.surcharge_enabled' => false,
            'general_house.standard_vat_rate' => '5.00',
            'general_house.payments.cash_enabled' => true,
            'general_house.payments.online_initiation_enabled' => false,
            'general_house.payments.online_gateway_confirm_enabled' => false,
            'general_house.payments.bank_transfer_enabled' => false,
            'general_house.payments.partial_payments_enabled' => true,
            'general_house.payments.overpayment_allowed' => false,
            'general_house.payments.driver_collection_enabled' => false,
            'general_house.corrections.credit_notes_enabled' => true,
            'general_house.corrections.debit_adjustments_enabled' => true,
            'general_house.corrections.refunds_enabled' => true,
            'general_house.corrections.payment_correction_execute_enabled' => false,
            'general_house.corrections.require_distinct_approver' => true,
            'general_house.corrections.require_distinct_payout_actor' => true,
        ]);
    }

    public function test_completed_order_issued_invoice_accepts_debit_and_original_unchanged(): void
    {
        $manager = User::factory()->manager()->create();
        [$invoice, $order] = $this->issueInvoiceWithOrder($manager, '50.00', 2, GhOrderStatus::COMPLETED);
        $originalTotal = (string) $invoice->total_payable;
        $originalVat = (string) $invoice->vat_amount;
        $lineId = (int) $invoice->lines->first()->id;

        $adj = app(GhDebitAdjustmentService::class)->createAndIssue($invoice, $manager, [
            'reason_code' => GhDebitAdjustmentReason::QUANTITY_CORRECTION,
            'reason_text' => 'Extra piece after completion',
            'idempotency_key' => 'da-completed-1',
            'lines' => [[
                'line_type' => GhDebitAdjustmentLineType::QUANTITY_INCREASE,
                'invoice_line_id' => $lineId,
                'quantity' => 1,
                'unit_price' => '50.00',
            ]],
        ]);

        $this->assertSame(GhDebitAdjustmentStatus::ISSUED, $adj->status);
        $this->assertMatchesRegularExpression('/^GH-DA-\d{6}$/', (string) $adj->number);
        $this->assertSame('52.50', (string) $adj->total_debit);
        $this->assertSame(GhOrderStatus::COMPLETED, $order->fresh()->status);

        $fresh = $invoice->fresh();
        $this->assertSame(GhInvoiceStatus::ISSUED, $fresh->status);
        $this->assertSame($originalTotal, (string) $fresh->total_payable);
        $this->assertSame($originalVat, (string) $fresh->vat_amount);
        $this->assertSame(1, $fresh->lines()->count());
        $this->assertSame(2, (int) $fresh->lines->first()->quantity);

        $bal = app(GhPaymentBalanceService::class)->forInvoice($fresh);
        $this->assertSame('105.00', $bal['invoice_total']);
        $this->assertSame('52.50', $bal['debits_issued']);
        $this->assertSame('157.50', $bal['net_invoiced']);
        $this->assertSame('157.50', $bal['remaining_due']);
    }

    public function test_additional_item_quantity_and_price_increase_vat(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager, '40.00', 1);
        $lineId = (int) $invoice->lines->first()->id;
        $offering = GhProductOffering::factory()->create(['standard_price' => '30.00']);
        $svc = app(GhDebitAdjustmentService::class);

        $additional = $svc->createAndIssue($invoice, $manager, [
            'reason_code' => GhDebitAdjustmentReason::ADDITIONAL_ITEMS,
            'reason_text' => 'Missed garment',
            'idempotency_key' => 'da-add-1',
            'lines' => [[
                'line_type' => GhDebitAdjustmentLineType::ADDITIONAL_ITEM,
                'offering_id' => $offering->id,
                'quantity' => 1,
                'unit_price' => '30.00',
            ]],
        ]);
        // 30 + 5% = 31.50
        $this->assertSame('31.50', (string) $additional->total_debit);

        $qty = $svc->createAndIssue($invoice, $manager, [
            'reason_code' => GhDebitAdjustmentReason::QUANTITY_CORRECTION,
            'reason_text' => 'Qty +1',
            'idempotency_key' => 'da-qty-1',
            'lines' => [[
                'line_type' => GhDebitAdjustmentLineType::QUANTITY_INCREASE,
                'invoice_line_id' => $lineId,
                'quantity' => 1,
                'unit_price' => '40.00',
            ]],
        ]);
        $this->assertSame('42.00', (string) $qty->total_debit);

        $price = $svc->createAndIssue($invoice, $manager, [
            'reason_code' => GhDebitAdjustmentReason::PRICE_CORRECTION,
            'reason_text' => 'Price delta',
            'idempotency_key' => 'da-price-1',
            'lines' => [[
                'line_type' => GhDebitAdjustmentLineType::PRICE_INCREASE,
                'invoice_line_id' => $lineId,
                'quantity' => 1,
                'unit_price' => '10.00',
            ]],
        ]);
        $this->assertSame('10.50', (string) $price->total_debit);

        $bal = app(GhPaymentBalanceService::class)->forInvoice($invoice->fresh());
        // Original 42.00 + 31.50 + 42.00 + 10.50 = 126.00
        $this->assertSame('42.00', $bal['invoice_total']);
        $this->assertSame('84.00', $bal['debits_issued']);
        $this->assertSame('126.00', $bal['net_invoiced']);
        $this->assertSame(3, GhDebitAdjustment::query()->where('status', GhDebitAdjustmentStatus::ISSUED)->count());
    }

    public function test_paid_partial_unpaid_balances_and_no_auto_payment(): void
    {
        $manager = User::factory()->manager()->create();
        $payments = app(GhPaymentService::class);
        $svc = app(GhDebitAdjustmentService::class);

        $paid = $this->issueInvoice($manager, '50.00', 2); // 105
        $payments->recordConfirmedCash($paid, $manager, ['amount' => '105.00', 'idempotency_key' => 'pay-full-da']);
        $svc->createAndIssue($paid, $manager, [
            'reason_code' => GhDebitAdjustmentReason::OTHER,
            'reason_text' => 'After full pay',
            'idempotency_key' => 'da-after-paid',
            'lines' => [[
                'line_type' => GhDebitAdjustmentLineType::QUANTITY_INCREASE,
                'invoice_line_id' => $paid->lines->first()->id,
                'quantity' => 1,
                'unit_price' => '50.00',
            ]],
        ]);
        $balPaid = app(GhPaymentBalanceService::class)->forInvoice($paid->fresh());
        $this->assertSame('52.50', $balPaid['remaining_due']);
        $this->assertSame(GhInvoicePaymentStatus::PARTIAL, $balPaid['payment_status']);
        $this->assertSame(1, GhPayment::query()->where('invoice_id', $paid->id)->count());

        $partial = $this->issueInvoice($manager, '50.00', 2);
        $payments->recordConfirmedCash($partial, $manager, ['amount' => '40.00', 'idempotency_key' => 'pay-partial-da']);
        $svc->createAndIssue($partial, $manager, [
            'reason_code' => GhDebitAdjustmentReason::OTHER,
            'reason_text' => 'After partial',
            'idempotency_key' => 'da-after-partial',
            'lines' => [[
                'line_type' => GhDebitAdjustmentLineType::PRICE_INCREASE,
                'invoice_line_id' => $partial->lines->first()->id,
                'quantity' => 1,
                'unit_price' => '20.00',
            ]],
        ]);
        $balPartial = app(GhPaymentBalanceService::class)->forInvoice($partial->fresh());
        // net = 105 + 21 = 126; paid 40; remaining 86
        $this->assertSame('86.00', $balPartial['remaining_due']);

        $unpaid = $this->issueInvoice($manager, '20.00', 1); // 21
        $svc->createAndIssue($unpaid, $manager, [
            'reason_code' => GhDebitAdjustmentReason::OTHER,
            'reason_text' => 'Unpaid bump',
            'idempotency_key' => 'da-unpaid',
            'lines' => [[
                'line_type' => GhDebitAdjustmentLineType::QUANTITY_INCREASE,
                'invoice_line_id' => $unpaid->lines->first()->id,
                'quantity' => 1,
                'unit_price' => '20.00',
            ]],
        ]);
        $balUnpaid = app(GhPaymentBalanceService::class)->forInvoice($unpaid->fresh());
        $this->assertSame('42.00', $balUnpaid['remaining_due']);
        $this->assertSame(GhInvoicePaymentStatus::UNPAID, $balUnpaid['payment_status']);
    }

    public function test_credit_note_plus_debit_and_idempotent_issue(): void
    {
        $m1 = User::factory()->manager()->create();
        $m2 = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($m1, '50.00', 2); // 105
        $lineId = (int) $invoice->lines->first()->id;

        $cnSvc = app(GhCreditNoteService::class);
        $cn = $cnSvc->createDraft($invoice, $m1, [
            'reason_code' => GhCreditNoteReason::QUANTITY_ERROR,
            'reason_text' => 'Credit one',
            'idempotency_key' => 'cn-with-da',
            'lines' => [['invoice_line_id' => $lineId, 'quantity_credited' => 1]],
        ]);
        $cnSvc->submitForApproval($cn, $m1);
        $cnSvc->approve($cn->fresh(), $m2);
        $cnSvc->issue($cn->fresh(), $m1);

        $daSvc = app(GhDebitAdjustmentService::class);
        $a = $daSvc->createAndIssue($invoice, $m1, [
            'reason_code' => GhDebitAdjustmentReason::ADDITIONAL_ITEMS,
            'reason_text' => 'Add back differently',
            'idempotency_key' => 'da-with-cn',
            'lines' => [[
                'line_type' => GhDebitAdjustmentLineType::QUANTITY_INCREASE,
                'invoice_line_id' => $lineId,
                'quantity' => 1,
                'unit_price' => '50.00',
            ]],
        ]);
        $b = $daSvc->createAndIssue($invoice, $m1, [
            'reason_code' => GhDebitAdjustmentReason::ADDITIONAL_ITEMS,
            'reason_text' => 'Add back differently',
            'idempotency_key' => 'da-with-cn',
            'lines' => [[
                'line_type' => GhDebitAdjustmentLineType::QUANTITY_INCREASE,
                'invoice_line_id' => $lineId,
                'quantity' => 1,
                'unit_price' => '50.00',
            ]],
        ]);
        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, GhDebitAdjustment::query()->count());

        $bal = app(GhPaymentBalanceService::class)->forInvoice($invoice->fresh());
        // 105 - 52.50 + 52.50 = 105
        $this->assertSame('52.50', $bal['credits_issued']);
        $this->assertSame('52.50', $bal['debits_issued']);
        $this->assertSame('105.00', $bal['net_invoiced']);
    }

    public function test_zero_negative_rejected_and_driver_forbidden(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $invoice = $this->issueInvoice($manager, '20.00', 1);
        $svc = app(GhDebitAdjustmentService::class);

        try {
            $svc->createDraft($invoice, $manager, [
                'reason_code' => GhDebitAdjustmentReason::OTHER,
                'reason_text' => 'Zero',
                'idempotency_key' => 'da-zero',
                'lines' => [[
                    'line_type' => GhDebitAdjustmentLineType::PRICE_INCREASE,
                    'invoice_line_id' => $invoice->lines->first()->id,
                    'quantity' => 1,
                    'unit_price' => '0',
                ]],
            ]);
            $this->fail('Zero price must fail');
        } catch (ValidationException $e) {
            $this->assertTrue(isset($e->errors()['lines.0.unit_price']) || isset($e->errors()['lines']));
        }

        $this->actingAs($driver)->get(route('gh.debit-adjustments.index'))->assertForbidden();
        $this->actingAs($driver)->get(route('gh.debit-adjustments.create', $invoice))->assertForbidden();
        $this->actingAs($driver)
            ->post(route('gh.debit-adjustments.store', $invoice), [
                'reason_code' => GhDebitAdjustmentReason::OTHER,
                'reason_text' => 'Hack',
                'idempotency_key' => 'da-driver',
                'issue_now' => 1,
                'lines' => [[
                    'line_type' => GhDebitAdjustmentLineType::QUANTITY_INCREASE,
                    'invoice_line_id' => $invoice->lines->first()->id,
                    'quantity' => 1,
                    'unit_price' => '20.00',
                ]],
            ])
            ->assertForbidden();
        $this->assertSame(0, GhDebitAdjustment::query()->count());
    }

    public function test_manager_http_issue_and_ui_routes(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager, '20.00', 1);

        $this->actingAs($manager)->get(route('gh.debit-adjustments.index'))->assertOk();
        $this->actingAs($manager)->get(route('gh.debit-adjustments.create', $invoice))->assertOk();

        $response = $this->actingAs($manager)->post(route('gh.debit-adjustments.store', $invoice), [
            'reason_code' => GhDebitAdjustmentReason::OTHER,
            'reason_text' => 'HTTP issue',
            'idempotency_key' => 'da-http-1',
            'issue_now' => 1,
            'lines' => [[
                'line_type' => GhDebitAdjustmentLineType::QUANTITY_INCREASE,
                'invoice_line_id' => $invoice->lines->first()->id,
                'quantity' => 1,
                'unit_price' => '20.00',
            ]],
        ]);
        $adj = GhDebitAdjustment::query()->sole();
        $response->assertRedirect(route('gh.debit-adjustments.show', $adj));
        $this->assertSame(GhDebitAdjustmentStatus::ISSUED, $adj->status);
        $this->actingAs($manager)->get(route('gh.debit-adjustments.show', $adj))->assertOk();
        $this->actingAs($manager)->get(route('gh.invoices.show', $invoice))->assertOk()
            ->assertSee(__('gh.invoice_corrections.create_debit_adjustment'), false);
    }

    public function test_concurrent_issue_stays_single_document(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($manager, '20.00', 1);
        $svc = app(GhDebitAdjustmentService::class);
        $draft = $svc->createDraft($invoice, $manager, [
            'reason_code' => GhDebitAdjustmentReason::OTHER,
            'reason_text' => 'Concurrent',
            'idempotency_key' => 'da-conc',
            'lines' => [[
                'line_type' => GhDebitAdjustmentLineType::QUANTITY_INCREASE,
                'invoice_line_id' => $invoice->lines->first()->id,
                'quantity' => 1,
                'unit_price' => '20.00',
            ]],
        ]);

        $first = $svc->issue($draft, $manager);
        $second = $svc->issue($draft->fresh(), $manager);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(GhDebitAdjustmentStatus::ISSUED, $second->status);
        $this->assertSame(1, GhDebitAdjustment::query()->where('status', GhDebitAdjustmentStatus::ISSUED)->count());
    }

    /**
     * @return array{0: GhInvoice, 1: GhOrder}
     */
    private function issueInvoiceWithOrder(User $manager, string $unitPrice, int $qty, string $orderStatus = GhOrderStatus::WORKING): array
    {
        $tz = config('general_house.timezone', 'Asia/Dubai');
        $zone = GhZone::factory()->create([
            'min_order_amount' => '0.00',
            'min_order_policy' => GhMinOrderPolicy::SURCHARGE,
        ]);
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $offering = GhProductOffering::factory()->create(['standard_price' => $unitPrice]);

        $order = GhOrder::factory()->create([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'timezone' => $tz,
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => Carbon::now('UTC'),
            'pickup_starts_at' => Carbon::now($tz)->subDay()->setTime(10, 0),
            'pickup_ends_at' => Carbon::now($tz)->subDay()->setTime(12, 0),
            'created_by' => $manager->id,
        ]);

        $line = GhOrderLine::factory()->create([
            'order_id' => $order->id,
            'offering_id' => $offering->id,
            'quantity' => $qty,
            'unit_price_estimate' => $unitPrice,
            'product_name_ar' => 'منتج',
            'product_name_en' => 'Product',
            'treatment_name_ar' => 'كي',
            'treatment_name_en' => 'Iron',
        ]);

        GhOrderReceivedLine::query()->create([
            'order_id' => $order->id,
            'order_line_id' => $line->id,
            'offering_id' => $offering->id,
            'quantity' => $qty,
            'discrepancy_type' => GhReceivedDiscrepancy::MATCH,
            'sort_order' => 0,
            'product_name_ar' => 'منتج',
            'product_name_en' => 'Product',
            'treatment_name_ar' => 'كي',
            'treatment_name_en' => 'Iron',
            'unit_price_estimate' => $unitPrice,
            'currency' => 'AED',
            'price_source' => 'STANDARD',
        ]);

        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order, $manager);
        $invoice = app(GhInvoiceIssuanceService::class)->issue($draft, $manager, (int) $draft->draft_version);

        if ($orderStatus !== GhOrderStatus::WORKING) {
            $order->forceFill([
                'status' => $orderStatus,
                'delivered_at' => $orderStatus === GhOrderStatus::COMPLETED ? Carbon::now('UTC') : null,
            ])->save();
        }

        return [$invoice->fresh(['lines']), $order->fresh()];
    }

    private function issueInvoice(User $manager, string $unitPrice, int $qty): GhInvoice
    {
        return $this->issueInvoiceWithOrder($manager, $unitPrice, $qty)[0];
    }
}
