<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossExpenseType;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossReceiptPurpose;
use App\Models\Gross\GrossSupplier;
use App\Models\Gross\GrossTreasuryAccount;
use App\Models\Gross\GrossTreasuryVoucher;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Services\Gross\ItemService;
use App\Services\Gross\PurchaseInvoiceService;
use App\Services\Gross\SupplierStatementService;
use App\Services\Gross\TreasuryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasuryVoucherTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_then_payment_updates_balances_and_statement(): void
    {
        $manager = User::factory()->manager()->create();
        $supplier = GrossSupplier::query()->create([
            'name' => 'Cairo Textiles',
            'phone' => '01001110000',
        ]);
        $item = app(ItemService::class)->create([
            'name' => 'Cloth',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        $purchases = app(PurchaseInvoiceService::class);
        $invoice = $purchases->createDraft($manager, [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'supplier_phone' => $supplier->phone,
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 10,
            'unit' => 'PIECE',
            'buy_price' => 20,
            'sale_price' => 30,
        ]]);
        $purchases->confirm($invoice, $manager);

        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();
        $other = GrossExpenseType::query()->where('name_en', 'Other')->firstOrFail();

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.treasury.index'))
            ->assertOk()
            ->assertSee('Treasury', false)
            ->assertSee('Payment voucher', false)
            ->assertSee('Total (Cash + Bank)', false);

        $this->actingAs($manager)
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_RECEIPT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_EXPENSE,
                'expense_type_id' => $other->id,
                'account_id' => $cash->id,
                'amount' => 500,
                'voucher_date' => now()->toDateString(),
                'notes' => 'Shipping fee cash',
            ])
            ->assertRedirect();

        $receipt = GrossTreasuryVoucher::query()->where('type', GrossTreasuryVoucher::TYPE_RECEIPT)->firstOrFail();
        $this->assertMatchesRegularExpression('/^RV-\d{6}-\d{4}$/', $receipt->voucher_number);
        $this->assertEqualsWithDelta(500.0, app(TreasuryService::class)->balanceOf($cash->fresh()), 0.01);

        $this->actingAs($manager)
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_PAYMENT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_SUPPLIER,
                'account_id' => $cash->id,
                'supplier_id' => $supplier->id,
                'amount' => 80,
                'voucher_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $payment = GrossTreasuryVoucher::query()->where('type', GrossTreasuryVoucher::TYPE_PAYMENT)->firstOrFail();
        $this->assertMatchesRegularExpression('/^PV-\d{6}-\d{4}$/', $payment->voucher_number);
        $this->assertEqualsWithDelta(420.0, app(TreasuryService::class)->balanceOf($cash->fresh()), 0.01);

        $totals = app(TreasuryService::class)->combinedTotalsByCurrency();
        $this->assertCount(1, $totals);
        $this->assertSame('EGP', $totals->first()['currency']);
        $this->assertSame('420.00', $totals->first()['balance']);

        $statement = app(SupplierStatementService::class)->build($supplier, ['currency' => 'EGP']);
        $this->assertSame('80.00', $statement['total_payments']);
        $this->assertSame('120.00', $statement['balance']);
        $paymentRow = collect($statement['entries'])->firstWhere('type', 'PAYMENT');
        $this->assertNotNull($paymentRow);
        $this->assertSame('80.00', $paymentRow['credit']);
    }

    public function test_expense_payment_does_not_hit_supplier_statement(): void
    {
        $manager = User::factory()->manager()->create();
        $supplier = GrossSupplier::query()->create([
            'name' => 'Cairo Textiles',
            'phone' => '01001110000',
        ]);
        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();
        $electricity = GrossExpenseType::query()->where('name_en', 'Electricity')->firstOrFail();

        $this->actingAs($manager)
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_RECEIPT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_EXPENSE,
                'expense_type_id' => $electricity->id,
                'account_id' => $cash->id,
                'amount' => 200,
                'voucher_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->actingAs($manager)
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_PAYMENT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_EXPENSE,
                'expense_type_id' => $electricity->id,
                'account_id' => $cash->id,
                'amount' => 50,
                'voucher_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertEqualsWithDelta(150.0, app(TreasuryService::class)->balanceOf($cash->fresh()), 0.01);

        $statement = app(SupplierStatementService::class)->build($supplier, ['currency' => 'EGP']);
        $this->assertSame('0.00', $statement['total_payments']);
        $this->assertSame([], $statement['entries']);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.treasury.index', ['purpose' => 'EXPENSE']))
            ->assertOk()
            ->assertSee('Electricity', false);
    }

    public function test_payment_create_preselects_supplier_from_query(): void
    {
        $manager = User::factory()->manager()->create();
        $supplier = GrossSupplier::query()->create([
            'name' => 'Preselect Mill',
            'phone' => '01009998888',
        ]);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.treasury.create', [
                'type' => 'PAYMENT',
                'purpose' => 'SUPPLIER',
                'supplier_id' => $supplier->id,
            ]))
            ->assertOk()
            ->assertSee('value="'.$supplier->id.'"', false)
            ->assertSee('selected', false)
            ->assertSee('Preselect Mill', false)
            ->assertSee('Account balance', false)
            ->assertSee('Purchases', false)
            ->assertSee('Balance', false);
    }

    public function test_receipt_create_shows_account_balance_context(): void
    {
        $manager = User::factory()->manager()->create();
        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();
        $purpose = GrossReceiptPurpose::factory()->create([
            'name_ar' => 'رأس مال',
            'name_en' => 'Owner capital',
        ]);

        $this->actingAs($manager)
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_RECEIPT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_OTHER,
                'receipt_purpose_id' => $purpose->id,
                'account_id' => $cash->id,
                'amount' => 250,
                'voucher_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.treasury.create', ['type' => 'RECEIPT']))
            ->assertOk()
            ->assertSee('Account balance', false)
            ->assertSee('250.00', false)
            ->assertSee('Collection increases the selected account balance.', false);
    }

    public function test_payment_blocked_when_cash_insufficient(): void
    {
        $manager = User::factory()->manager()->create();
        $supplier = GrossSupplier::query()->create([
            'name' => 'Mill',
            'phone' => '01110000000',
        ]);
        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->from(route('gross.treasury.create', ['type' => 'PAYMENT']))
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_PAYMENT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_SUPPLIER,
                'account_id' => $cash->id,
                'supplier_id' => $supplier->id,
                'amount' => 10,
                'voucher_date' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, GrossTreasuryVoucher::query()->count());
    }

    public function test_other_receipt_increases_balance_without_supplier_or_expense(): void
    {
        $manager = User::factory()->manager()->create();
        $supplier = GrossSupplier::query()->create([
            'name' => 'Cairo Textiles',
            'phone' => '01001110000',
        ]);
        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();
        $purpose = GrossReceiptPurpose::factory()->create([
            'name_ar' => 'رأس مال المالك',
            'name_en' => 'Owner capital',
        ]);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.treasury.create', ['type' => 'RECEIPT']))
            ->assertOk()
            ->assertSee('value="OTHER:'.$purpose->id.'"', false)
            ->assertSee('Owner capital', false)
            ->assertSee('Manage receipt purposes', false)
            ->assertDontSee('value="EXPENSE"', false)
            ->assertDontSee('value="SUPPLIER"', false)
            ->assertDontSee('value="COURIER_COD"', false);

        $this->actingAs($manager)
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_RECEIPT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_OTHER,
                'receipt_purpose_id' => $purpose->id,
                'account_id' => $cash->id,
                'amount' => 1000,
                'voucher_date' => now()->toDateString(),
                'notes' => 'Owner capital injection',
            ])
            ->assertRedirect();

        $receipt = GrossTreasuryVoucher::query()->where('purpose', GrossTreasuryVoucher::PURPOSE_OTHER)->firstOrFail();
        $this->assertSame(GrossTreasuryVoucher::TYPE_RECEIPT, $receipt->type);
        $this->assertSame($purpose->id, (int) $receipt->receipt_purpose_id);
        $this->assertNull($receipt->supplier_id);
        $this->assertNull($receipt->expense_type_id);
        $this->assertNull($receipt->sales_invoice_id);
        $this->assertEqualsWithDelta(1000.0, app(TreasuryService::class)->balanceOf($cash->fresh()), 0.01);

        $statement = app(SupplierStatementService::class)->build($supplier, ['currency' => 'EGP']);
        $this->assertSame([], $statement['entries']);

        $this->actingAs($manager)
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_RECEIPT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_OTHER,
                'account_id' => $cash->id,
                'amount' => 50,
                'voucher_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('receipt_purpose_id');

        $this->actingAs($manager)
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_PAYMENT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_OTHER,
                'receipt_purpose_id' => $purpose->id,
                'account_id' => $cash->id,
                'amount' => 100,
                'voucher_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('purpose');
    }

    public function test_sales_forbidden_accountant_allowed(): void
    {
        $manager = User::factory()->manager()->create();
        $sales = User::factory()->sales()->create();
        ValuationAssignment::query()->create([
            'user_id' => $sales->id,
            'assigned_by' => $manager->id,
            'module' => 'product',
        ]);
        $accountant = User::factory()->accountant()->create();
        ValuationAssignment::query()->create([
            'user_id' => $accountant->id,
            'assigned_by' => $manager->id,
            'module' => 'product',
        ]);

        $this->actingAs($sales->fresh())
            ->get(route('gross.treasury.index'))
            ->assertForbidden();

        $this->actingAs($accountant->fresh())
            ->get(route('gross.treasury.index'))
            ->assertOk();
    }
}
