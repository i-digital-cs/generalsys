<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSupplier;
use App\Models\User;
use App\Services\Gross\ItemService;
use App\Services\Gross\PurchaseInvoiceService;
use App\Services\Gross\PurchaseReturnService;
use App\Services\Gross\SupplierStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_statement_running_balance_purchases_minus_returns(): void
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
        $lineId = $invoice->fresh()->lines->first()->id;

        app(PurchaseReturnService::class)->create($manager, $invoice->fresh(), [
            ['purchase_invoice_line_id' => $lineId, 'qty' => 2],
        ]);

        $statement = app(SupplierStatementService::class)->build($supplier, ['currency' => 'EGP']);

        $this->assertCount(2, $statement['entries']);
        $this->assertSame('PURCHASE', $statement['entries'][0]['type']);
        $this->assertSame('200.00', $statement['entries'][0]['debit']);
        $this->assertSame('200.00', $statement['entries'][0]['balance']);
        $this->assertSame('RETURN', $statement['entries'][1]['type']);
        $this->assertSame('40.00', $statement['entries'][1]['credit']);
        $this->assertSame('160.00', $statement['entries'][1]['balance']);
        $this->assertSame('200.00', $statement['total_purchases']);
        $this->assertSame('40.00', $statement['total_returns']);
        $this->assertSame('0.00', $statement['total_payments']);
        $this->assertSame('160.00', $statement['balance']);
    }

    public function test_statement_page_and_ignores_other_currency(): void
    {
        $manager = User::factory()->manager()->create();
        $supplier = GrossSupplier::query()->create([
            'name' => 'Alex Mill',
            'phone' => '01112223333',
        ]);
        $item = app(ItemService::class)->create([
            'name' => 'Yarn',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $purchases = app(PurchaseInvoiceService::class);
        $egp = $purchases->createDraft($manager, [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'supplier_phone' => $supplier->phone,
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'buy_price' => 50,
            'sale_price' => 60,
        ]]);
        $purchases->confirm($egp, $manager);

        $aed = $purchases->createDraft($manager, [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'supplier_phone' => $supplier->phone,
            'currency' => 'AED',
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'buy_price' => 100,
            'sale_price' => 120,
        ]]);
        $purchases->confirm($aed, $manager);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.suppliers.statement', $supplier))
            ->assertOk()
            ->assertSee('Statement — Alex Mill', false)
            ->assertSee('Purchases − returns − payments', false)
            ->assertSee($egp->invoice_number, false)
            ->assertDontSee($aed->invoice_number, false);

        $this->actingAs($manager)
            ->get(route('gross.suppliers.show', $supplier))
            ->assertOk()
            ->assertSee(route('gross.suppliers.statement', $supplier), false);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.suppliers.index'))
            ->assertOk()
            ->assertSee('Account statement', false)
            ->assertSee('50.00', false);
    }

    public function test_draft_purchases_excluded(): void
    {
        $manager = User::factory()->manager()->create();
        $supplier = GrossSupplier::query()->create([
            'name' => 'Draft Co',
            'phone' => '01009998888',
        ]);
        $item = app(ItemService::class)->create([
            'name' => 'Button',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        app(PurchaseInvoiceService::class)->createDraft($manager, [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'supplier_phone' => $supplier->phone,
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 3,
            'unit' => 'PIECE',
            'buy_price' => 10,
            'sale_price' => 15,
        ]]);

        $statement = app(SupplierStatementService::class)->build($supplier, ['currency' => 'EGP']);
        $this->assertSame([], $statement['entries']);
        $this->assertSame('0.00', $statement['balance']);
    }

    public function test_summaries_for_suppliers_matches_statement_totals(): void
    {
        $manager = User::factory()->manager()->create();
        $supplier = GrossSupplier::query()->create([
            'name' => 'Summary Mill',
            'phone' => '01005556666',
        ]);
        $item = app(ItemService::class)->create([
            'name' => 'Thread',
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
            'qty' => 5,
            'unit' => 'PIECE',
            'buy_price' => 100,
            'sale_price' => 150,
        ]]);
        $purchases->confirm($invoice, $manager);

        $statement = app(SupplierStatementService::class);
        $summary = $statement->summariesForSuppliers([$supplier->id], 'EGP')[$supplier->id];
        $built = $statement->build($supplier, ['currency' => 'EGP']);

        $this->assertSame($built['total_purchases'], $summary['total_purchases']);
        $this->assertSame($built['total_returns'], $summary['total_returns']);
        $this->assertSame($built['total_payments'], $summary['total_payments']);
        $this->assertSame($built['balance'], $summary['balance']);
    }
}
