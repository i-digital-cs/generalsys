<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCreditNote;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderLine;
use App\Models\GeneralHouse\GhOrderReceivedLine;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhRefund;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Corrections\GhCreditNoteService;
use App\Services\GeneralHouse\Corrections\GhFinancialCorrectionService;
use App\Services\GeneralHouse\Corrections\GhRefundService;
use App\Services\GeneralHouse\Invoices\GhInvoiceIssuanceService;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Services\GeneralHouse\Payments\GhPaymentBalanceService;
use App\Services\GeneralHouse\Payments\GhPaymentService;
use App\Support\GeneralHouse\GhCreditNoteReason;
use App\Support\GeneralHouse\GhCreditNoteStatus;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhFinancialCorrectionStatus;
use App\Support\GeneralHouse\GhInvoicePaymentStatus;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhReceivedDiscrepancy;
use App\Support\GeneralHouse\GhRefundStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['orders', 'invoices', 'payments', 'credit_notes', 'refunds', 'financial_corrections'] as $seq) {
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
            'general_house.corrections.refunds_enabled' => true,
            'general_house.corrections.payment_correction_execute_enabled' => false,
            'general_house.corrections.require_distinct_approver' => true,
            'general_house.corrections.require_distinct_payout_actor' => true,
        ]);
    }

    public function test_partial_credit_note_and_excess_rejected(): void
    {
        $m1 = User::factory()->manager()->create();
        $m2 = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($m1, '50.00', 2); // 105.00
        $lineId = (int) $invoice->lines->first()->id;

        $svc = app(GhCreditNoteService::class);
        $draft = $svc->createDraft($invoice, $m1, [
            'reason_code' => GhCreditNoteReason::QUANTITY_ERROR,
            'reason_text' => 'One item excess',
            'idempotency_key' => 'cn-partial-1',
            'lines' => [['invoice_line_id' => $lineId, 'quantity_credited' => 1]],
        ]);
        $this->assertSame(GhCreditNoteStatus::DRAFT, $draft->status);
        $this->assertSame('52.50', (string) $draft->total_credit);

        $svc->submitForApproval($draft, $m1);
        $svc->approve($draft->fresh(), $m2);
        $issued = $svc->issue($draft->fresh(), $m1);
        $this->assertSame(GhCreditNoteStatus::ISSUED, $issued->status);
        $this->assertMatchesRegularExpression('/^GH-CN-\d{6}$/', (string) $issued->number);

        $balance = app(GhPaymentBalanceService::class)->forInvoice($invoice->fresh());
        $this->assertSame('52.50', $balance['credits_issued']);
        $this->assertSame('52.50', $balance['net_invoiced']);
        $this->assertSame(GhInvoiceStatus::ISSUED, $invoice->fresh()->status);
        $this->assertSame('105.00', (string) $invoice->fresh()->total_payable);

        $this->expectException(ValidationException::class);
        $svc->createDraft($invoice, $m1, [
            'reason_code' => GhCreditNoteReason::QUANTITY_ERROR,
            'reason_text' => 'Would exceed',
            'idempotency_key' => 'cn-excess',
            'lines' => [['invoice_line_id' => $lineId, 'quantity_credited' => 2]],
        ]);
    }

    public function test_full_credit_then_refund_payout_and_sod(): void
    {
        $m1 = User::factory()->manager()->create();
        $m2 = User::factory()->manager()->create();
        $m3 = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($m1, '50.00', 2);
        $lineId = (int) $invoice->lines->first()->id;

        app(GhPaymentService::class)->recordConfirmedCash($invoice, $m1, [
            'amount' => '105.00',
            'idempotency_key' => 'pay-full',
        ]);

        $cnSvc = app(GhCreditNoteService::class);
        $cn = $cnSvc->createDraft($invoice, $m1, [
            'reason_code' => GhCreditNoteReason::SERVICE_REDUCTION,
            'reason_text' => 'Full credit after payment',
            'idempotency_key' => 'cn-full',
            'lines' => [['invoice_line_id' => $lineId, 'quantity_credited' => 2]],
        ]);
        $cnSvc->submitForApproval($cn, $m1);
        $cnSvc->approve($cn->fresh(), $m2);
        $cnSvc->issue($cn->fresh(), $m1);

        $bal = app(GhPaymentBalanceService::class)->forInvoice($invoice->fresh());
        $this->assertSame('0.00', $bal['net_invoiced']);
        $this->assertSame('105.00', $bal['customer_credit']);
        $this->assertSame(GhInvoicePaymentStatus::PAID, $bal['payment_status']);

        $rfSvc = app(GhRefundService::class);
        $refund = $rfSvc->request($invoice->fresh(), $m1, [
            'amount' => '105.00',
            'reason_code' => 'CREDIT_NOTE_PAYOUT',
            'reason_text' => 'Return cash after full credit',
            'credit_note_id' => $cn->fresh()->id,
            'idempotency_key' => 'rf-1',
        ]);
        $rfSvc->submitForApproval($refund, $m1);

        try {
            $rfSvc->approve($refund->fresh(), $m1);
            $this->fail('Same actor must not approve');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('approver', $e->errors());
        }

        $rfSvc->approve($refund->fresh(), $m2);

        try {
            $rfSvc->payOut($refund->fresh(), $m2);
            $this->fail('Approver must not pay out');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payout', $e->errors());
        }

        $paid = $rfSvc->payOut($refund->fresh(), $m3);
        $this->assertSame(GhRefundStatus::PAID_OUT, $paid->status);
        $this->assertMatchesRegularExpression('/^GH-RF-\d{6}$/', (string) $paid->reference);

        $after = app(GhPaymentBalanceService::class)->forInvoice($invoice->fresh());
        $this->assertSame('0.00', $after['customer_credit']);
        $this->assertSame('105.00', $after['refunds_paid_out']);
        $this->assertSame('0.00', $after['cash_net']);
        $this->assertSame('105.00', (string) $invoice->fresh()->total_payable);

        // Duplicate payout is idempotent
        $again = $rfSvc->payOut($paid, $m3);
        $this->assertSame(GhRefundStatus::PAID_OUT, $again->status);
        $this->assertSame(1, GhRefund::query()->where('invoice_id', $invoice->id)->where('status', GhRefundStatus::PAID_OUT)->count());
    }

    public function test_unpaid_approved_refund_excluded_from_cash_net(): void
    {
        $m1 = User::factory()->manager()->create();
        $m2 = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($m1, '50.00', 2);
        $lineId = (int) $invoice->lines->first()->id;
        app(GhPaymentService::class)->recordConfirmedCash($invoice, $m1, [
            'amount' => '105.00',
            'idempotency_key' => 'pay-2',
        ]);
        $cnSvc = app(GhCreditNoteService::class);
        $cn = $cnSvc->createDraft($invoice, $m1, [
            'reason_code' => GhCreditNoteReason::OTHER,
            'reason_text' => 'Half credit',
            'idempotency_key' => 'cn-half',
            'lines' => [['invoice_line_id' => $lineId, 'quantity_credited' => 1]],
        ]);
        $cnSvc->submitForApproval($cn, $m1);
        $cnSvc->approve($cn->fresh(), $m2);
        $cnSvc->issue($cn->fresh(), $m1);

        $rfSvc = app(GhRefundService::class);
        $refund = $rfSvc->request($invoice->fresh(), $m1, [
            'amount' => '52.50',
            'reason_code' => 'CUSTOMER_REFUND',
            'reason_text' => 'Pending payout',
            'idempotency_key' => 'rf-unpaid',
        ]);
        $rfSvc->submitForApproval($refund, $m1);
        $rfSvc->approve($refund->fresh(), $m2);

        $bal = app(GhPaymentBalanceService::class)->forInvoice($invoice->fresh());
        $this->assertSame('0.00', $bal['refunds_paid_out']);
        $this->assertSame('105.00', $bal['cash_net']);
        $this->assertSame('52.50', $bal['customer_credit']);
    }

    public function test_refund_above_available_and_driver_forbidden(): void
    {
        $m1 = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($m1, '50.00', 2);
        app(GhPaymentService::class)->recordConfirmedCash($invoice, $m1, [
            'amount' => '105.00',
            'idempotency_key' => 'pay-3',
        ]);

        $this->expectException(ValidationException::class);
        app(GhRefundService::class)->request($invoice, $m1, [
            'amount' => '10.00',
            'reason_code' => 'CUSTOMER_REFUND',
            'reason_text' => 'No credit yet',
            'idempotency_key' => 'rf-over',
        ]);
    }

    public function test_driver_cannot_access_correction_routes(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $invoice = $this->issueInvoice($manager, '20.00', 1);

        $this->actingAs($driver)->get(route('gh.credit-notes.index'))->assertForbidden();
        $this->actingAs($driver)->get(route('gh.refunds.index'))->assertForbidden();
        $this->actingAs($driver)->get(route('gh.financial-corrections.index'))->assertForbidden();
        $this->actingAs($driver)->get(route('gh.credit-notes.create', $invoice))->assertForbidden();
    }

    public function test_payment_correction_request_allowed_execute_blocked(): void
    {
        $m1 = User::factory()->manager()->create();
        $m2 = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($m1, '50.00', 2);
        $payment = app(GhPaymentService::class)->recordConfirmedCash($invoice, $m1, [
            'amount' => '40.00',
            'idempotency_key' => 'pay-err',
        ]);

        $fc = app(GhFinancialCorrectionService::class);
        $corr = $fc->requestPaymentRecordingError($payment, $m1, [
            'reason_text' => 'Wrong amount recorded',
            'idempotency_key' => 'fc-1',
        ]);
        $fc->submitForApproval($corr, $m1);
        $fc->approve($corr->fresh(), $m2);
        $this->assertSame(GhFinancialCorrectionStatus::APPROVED, $corr->fresh()->status);

        try {
            $fc->execute($corr->fresh(), $m1);
            $this->fail('Execute must stay blocked');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('execute', $e->errors());
        }

        $this->assertSame('CONFIRMED', $payment->fresh()->status);
        $this->assertSame(GhInvoiceStatus::ISSUED, $invoice->fresh()->status);
        $this->assertSame(GhFinancialCorrectionStatus::APPROVED, $corr->fresh()->status);
    }

    public function test_manager_lists_render(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->get(route('gh.credit-notes.index'))->assertOk();
        $this->actingAs($manager)->get(route('gh.refunds.index'))->assertOk();
        $this->actingAs($manager)->get(route('gh.financial-corrections.index'))->assertOk();
    }

    public function test_idempotent_credit_note_create(): void
    {
        $m1 = User::factory()->manager()->create();
        $invoice = $this->issueInvoice($m1, '50.00', 2);
        $lineId = (int) $invoice->lines->first()->id;
        $svc = app(GhCreditNoteService::class);
        $a = $svc->createDraft($invoice, $m1, [
            'reason_code' => GhCreditNoteReason::OTHER,
            'reason_text' => 'Idem',
            'idempotency_key' => 'same-key',
            'lines' => [['invoice_line_id' => $lineId, 'quantity_credited' => 1]],
        ]);
        $b = $svc->createDraft($invoice, $m1, [
            'reason_code' => GhCreditNoteReason::OTHER,
            'reason_text' => 'Idem',
            'idempotency_key' => 'same-key',
            'lines' => [['invoice_line_id' => $lineId, 'quantity_credited' => 1]],
        ]);
        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, GhCreditNote::query()->count());
    }

    private function issueInvoice(User $manager, string $unitPrice, int $qty): GhInvoice
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

        return app(GhInvoiceIssuanceService::class)->issue($draft, $manager, (int) $draft->draft_version);
    }
}
