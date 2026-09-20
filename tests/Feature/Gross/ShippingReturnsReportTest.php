<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossStockMovement;
use App\Models\User;
use App\Services\Gross\AllocationService;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ShipsGrossSalesToCourier;
use Tests\TestCase;

class ShippingReturnsReportTest extends TestCase
{
    use RefreshDatabase;
    use ShipsGrossSalesToCourier;

    private User $manager;

    private GrossItem $item;

    private GrossShippingCompany $company;

    private SalesInvoiceService $invoices;

    private AllocationService $allocations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->invoices = app(SalesInvoiceService::class);
        $this->allocations = app(AllocationService::class);

        $this->item = app(ItemService::class)->create([
            'name' => 'Dress',
            'reorder_level' => 2,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($this->item, 30, GrossStockMovement::UNIT_PIECE, $this->manager);

        $this->company = app(ShippingCompanyService::class)->create([
            'name' => 'Bosta',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_returns_report_lists_partial_and_refuse_events(): void
    {
        $partialInvoice = $this->confirmInvoice('Partial Customer', 10);
        $this->allocations->partialReturn(
            $partialInvoice->lines->first()->allocation,
            3,
            $this->manager,
        );

        $refuseInvoice = $this->confirmInvoice('Refuse Customer', 6);
        $this->invoices->refuse($refuseInvoice->fresh(), $this->manager, 'no answer');

        $this->withSession(['locale' => 'en'])->actingAs($this->manager)
            ->get(route('gross.shipping.returns', $this->company))
            ->assertOk()
            ->assertSee(__('gross.shipping.returns_title', ['name' => $this->company->name]), false)
            ->assertSee($partialInvoice->invoice_number, false)
            ->assertSee('Partial Customer', false)
            ->assertSee(__('gross.shipping.return_kind.PARTIAL'), false)
            ->assertSee('Refuse Customer', false)
            ->assertSee(__('gross.shipping.return_kind.REFUSE'), false)
            ->assertSee(__('gross.shipping.return_location.WITH_COURIER'), false)
            ->assertSee(__('gross.shipping.returns_col_location'), false)
            ->assertSee('Dress', false)
            ->assertSee('>3<', false)
            ->assertSee('>6<', false);
    }

    public function test_returns_report_totals_and_date_filter(): void
    {
        $invoice = $this->confirmInvoice('Filter Customer', 5);
        $this->allocations->partialReturn(
            $invoice->lines->first()->allocation,
            2,
            $this->manager,
        );

        $today = now()->toDateString();

        $this->actingAs($this->manager)
            ->get(route('gross.shipping.returns', [
                'shipping' => $this->company,
                'from' => $today,
                'to' => $today,
            ]))
            ->assertOk()
            ->assertSee(__('gross.shipping.returns_total_events'), false)
            ->assertSee('>1<', false)
            ->assertSee('>2<', false);

        $future = now()->addDays(10)->toDateString();
        $this->actingAs($this->manager)
            ->get(route('gross.shipping.returns', [
                'shipping' => $this->company,
                'from' => $future,
            ]))
            ->assertOk()
            ->assertSee(__('gross.shipping.returns_empty'), false);
    }

    public function test_other_shipping_company_returns_are_excluded(): void
    {
        $other = app(ShippingCompanyService::class)->create([
            'name' => 'Other Courier',
            'status' => 'ACTIVE',
        ]);

        $invoice = $this->confirmInvoice('Scoped Customer', 4);
        $this->allocations->partialReturn(
            $invoice->lines->first()->allocation,
            1,
            $this->manager,
        );

        $this->actingAs($this->manager)
            ->get(route('gross.shipping.returns', $other))
            ->assertOk()
            ->assertSee(__('gross.shipping.returns_empty'), false);
    }

    private function confirmInvoice(string $customerName, int $qty): GrossSalesInvoice
    {
        $invoice = $this->invoices->createDraft($this->manager, [
            'customer_name' => $customerName,
            'customer_phone' => '01001234567',
            'shipping_company_id' => $this->company->id,
            'currency' => 'EGP',
            'shipping_fee' => 0,
        ], [[
            'item_id' => $this->item->id,
            'qty' => $qty,
            'unit' => 'PIECE',
            'unit_price' => 100,
        ]]);

        return $this->shipToCourier($invoice, $this->manager)->load(['lines.allocation']);
    }
}
