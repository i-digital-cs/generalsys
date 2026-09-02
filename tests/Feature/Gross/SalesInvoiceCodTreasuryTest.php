<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossStockMovement;
use App\Models\Gross\GrossTreasuryAccount;
use App\Models\Gross\GrossTreasuryVoucher;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCollectionStatementService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\StockService;
use App\Services\Gross\TreasuryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesInvoiceCodTreasuryTest extends TestCase
{
    use RefreshDatabase;

    private function confirmedInvoice(User $manager): GrossSalesInvoice
    {
        $company = app(ShippingCompanyService::class)->create(['name' => 'Bosta', 'status' => 'ACTIVE']);
        $item = app(ItemService::class)->create(['name' => 'Dress', 'status' => GrossItem::STATUS_ACTIVE]);
        app(StockService::class)->adjustIn($item, 5, GrossStockMovement::UNIT_PIECE, $manager);

        return app(SalesInvoiceService::class)->confirm(
            app(SalesInvoiceService::class)->createDraft($manager, [
                'customer_name' => 'Ali',
                'customer_phone' => '0100',
                'shipping_company_id' => $company->id,
                'currency' => 'EGP',
                'shipping_fee' => 20,
            ], [[
                'item_id' => $item->id,
                'qty' => 2,
                'unit' => 'PIECE',
                'unit_price' => 50,
            ]]),
            $manager,
        );
    }

    public function test_collect_cod_with_treasury_posts_receipt(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->confirmedInvoice($manager);
        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();

        $this->actingAs($manager)
            ->post(route('gross.sales.collect-cod', $invoice), [
                'post_to_treasury' => '1',
                'treasury_account_id' => $cash->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame(GrossSalesInvoice::COD_COLLECTED, $invoice->cod_collection_status);
        $this->assertSame('100.00', $invoice->cod_collected_amount);

        $voucher = GrossTreasuryVoucher::query()
            ->where('purpose', GrossTreasuryVoucher::PURPOSE_COURIER_COD)
            ->where('sales_invoice_id', $invoice->id)
            ->first();
        $this->assertNotNull($voucher);
        $this->assertSame('100.00', $voucher->amount);
        $this->assertEqualsWithDelta(100.0, app(TreasuryService::class)->balanceOf($cash->fresh()), 0.01);
    }

    public function test_collect_cod_without_treasury_allows_manual_post_later(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->confirmedInvoice($manager);
        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();

        app(SalesInvoiceService::class)->collectCod($invoice, $manager);

        $this->actingAs($manager)
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_RECEIPT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_COURIER_COD,
                'account_id' => $cash->id,
                'sales_invoice_id' => $invoice->id,
                'voucher_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertSame(1, GrossTreasuryVoucher::query()
            ->where('purpose', GrossTreasuryVoucher::PURPOSE_COURIER_COD)
            ->where('sales_invoice_id', $invoice->id)
            ->count());
    }

    public function test_double_treasury_post_blocked(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->confirmedInvoice($manager);
        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();

        app(SalesInvoiceService::class)->collectCod($invoice, $manager, [
            'post_to_treasury' => true,
            'treasury_account_id' => $cash->id,
        ]);

        $this->actingAs($manager)
            ->from(route('gross.treasury.create', ['type' => 'RECEIPT', 'purpose' => 'COURIER_COD']))
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_RECEIPT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_COURIER_COD,
                'account_id' => $cash->id,
                'sales_invoice_id' => $invoice->id,
                'amount' => 120,
                'voucher_date' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('amount');

        $this->assertSame(1, GrossTreasuryVoucher::query()
            ->where('sales_invoice_id', $invoice->id)
            ->count());
    }

    public function test_sales_cannot_post_treasury_on_collect(): void
    {
        $manager = User::factory()->manager()->create();
        $sales = User::factory()->sales()->create();
        ValuationAssignment::query()->create([
            'user_id' => $sales->id,
            'assigned_by' => $manager->id,
            'module' => 'product',
        ]);

        $invoice = $this->confirmedInvoice($manager);
        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();

        $this->actingAs($sales->fresh())
            ->post(route('gross.sales.collect-cod', $invoice), [
                'post_to_treasury' => '1',
                'treasury_account_id' => $cash->id,
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(GrossSalesInvoice::COD_COLLECTED, $invoice->cod_collection_status);
        $this->assertSame(0, GrossTreasuryVoucher::query()->where('sales_invoice_id', $invoice->id)->count());
    }

    public function test_statement_shows_treasury_receipt_instead_of_collection_when_posted(): void
    {
        $manager = User::factory()->manager()->create();
        $invoice = $this->confirmedInvoice($manager);
        $company = $invoice->shippingCompany;
        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();

        app(SalesInvoiceService::class)->collectCod($invoice, $manager, [
            'post_to_treasury' => true,
            'treasury_account_id' => $cash->id,
        ]);

        $built = app(ShippingCollectionStatementService::class)->build($company, ['currency' => 'EGP']);

        $types = collect($built['entries'])->pluck('type')->all();
        $this->assertContains('TREASURY_RECEIPT', $types);
        $this->assertNotContains('COLLECTION', $types);
        $this->assertSame('0.00', $built['balance']);
    }
}
