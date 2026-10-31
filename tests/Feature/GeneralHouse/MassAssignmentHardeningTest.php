<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhPayment;
use App\Support\GeneralHouse\GhInvoicePaymentStatus;
use App\Support\GeneralHouse\GhInvoiceStatus;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhPaymentStatus;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-H2: empty/restricted $fillable blocks unauthorized mass assignment (throws when guarded).
 */
class MassAssignmentHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_rejects_mass_assignment_of_status_and_totals(): void
    {
        $this->assertSame([], (new GhInvoice)->getFillable());

        $invoice = new GhInvoice;
        $invoice->forceFill([
            'status' => GhInvoiceStatus::DRAFT,
            'payment_status' => GhInvoicePaymentStatus::UNPAID,
            'total_payable' => '10.00',
        ]);
        $this->assertSame(GhInvoiceStatus::DRAFT, $invoice->status);

        $this->expectException(MassAssignmentException::class);
        $invoice->fill([
            'status' => GhInvoiceStatus::ISSUED,
            'payment_status' => GhInvoicePaymentStatus::PAID,
            'total_payable' => '9999.00',
        ]);
    }

    public function test_payment_rejects_mass_assignment_of_status_and_amount(): void
    {
        $this->assertSame([], (new GhPayment)->getFillable());

        $payment = new GhPayment;
        $payment->forceFill([
            'status' => GhPaymentStatus::PENDING,
            'amount' => '25.00',
        ]);
        $this->assertSame(GhPaymentStatus::PENDING, $payment->status);

        $this->expectException(MassAssignmentException::class);
        $payment->fill([
            'status' => GhPaymentStatus::CONFIRMED,
            'amount' => '1.00',
        ]);
    }

    public function test_order_allows_notes_fill_but_discards_status_and_drivers(): void
    {
        $order = GhOrder::factory()->create([
            'status' => GhOrderStatus::LATER,
            'pickup_driver_id' => null,
            'notes' => 'before',
        ]);

        $order->fill([
            'notes' => 'client note ok',
            'status' => GhOrderStatus::COMPLETED,
            'pickup_driver_id' => 999,
            'delivery_driver_id' => 998,
        ]);
        $order->save();

        $fresh = $order->fresh();
        $this->assertSame('client note ok', $fresh->notes);
        $this->assertSame(GhOrderStatus::LATER, $fresh->status);
        $this->assertNull($fresh->pickup_driver_id);
        $this->assertNull($fresh->delivery_driver_id);
    }
}
