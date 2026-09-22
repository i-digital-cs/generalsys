<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossPurchaseInvoice;
use App\Models\Gross\GrossStockBalance;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Services\Gross\ItemService;
use App\Services\Gross\PurchaseInvoiceService;
use App\Services\Gross\PurchaseReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-15.4 — purchase update/delete any status: Manager (Admin) only.
 */
class PurchaseInvoiceAdminMutateTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $accountant;

    private GrossPurchaseInvoice $draft;

    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->accountant = User::factory()->accountant()->create();

        ValuationAssignment::query()->create([
            'user_id' => $this->accountant->id,
            'assigned_by' => $this->manager->id,
            'module' => 'product',
        ]);

        $item = app(ItemService::class)->create([
            'name' => 'Fabric',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        $this->itemId = $item->id;

        $this->draft = app(PurchaseInvoiceService::class)->createDraft($this->manager, [
            'supplier_name' => 'Mill',
            'supplier_phone' => '0100',
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 2,
            'unit' => 'PIECE',
            'buy_price' => 10,
            'sale_price' => 20,
        ]]);
    }

    public function test_accountant_cannot_update_or_delete_manager_can_on_draft(): void
    {
        $this->actingAs($this->accountant->fresh())
            ->get(route('gross.purchases.edit', $this->draft))
            ->assertForbidden();

        $this->actingAs($this->accountant->fresh())
            ->delete(route('gross.purchases.destroy', $this->draft))
            ->assertForbidden();

        $this->assertDatabaseHas('gross_purchase_invoices', ['id' => $this->draft->id]);

        $this->actingAs($this->manager)
            ->put(route('gross.purchases.update', $this->draft), $this->payload('Mill Updated', 3, 12))
            ->assertRedirect(route('gross.purchases.show', $this->draft));

        $this->assertSame('Mill Updated', $this->draft->fresh()->supplier_name);

        $this->actingAs($this->manager)
            ->delete(route('gross.purchases.destroy', $this->draft->fresh()))
            ->assertRedirect(route('gross.purchases.index'));

        $this->assertDatabaseMissing('gross_purchase_invoices', ['id' => $this->draft->id]);
    }

    public function test_manager_can_update_and_delete_confirmed_with_stock_adjust(): void
    {
        $service = app(PurchaseInvoiceService::class);
        $confirmed = $service->confirm($this->draft, $this->manager);
        $this->assertSame(2, (int) GrossStockBalance::query()->where('item_id', $this->itemId)->value('qty_on_hand_pieces'));

        $this->actingAs($this->manager)
            ->get(route('gross.purchases.edit', $confirmed))
            ->assertOk();

        $this->actingAs($this->manager)
            ->put(route('gross.purchases.update', $confirmed), $this->payload('Mill Confirmed', 5, 11))
            ->assertRedirect(route('gross.purchases.show', $confirmed));

        $confirmed->refresh();
        $this->assertSame(GrossPurchaseInvoice::STATUS_CONFIRMED, $confirmed->status);
        $this->assertSame('Mill Confirmed', $confirmed->supplier_name);
        $this->assertSame(5, (int) GrossStockBalance::query()->where('item_id', $this->itemId)->value('qty_on_hand_pieces'));
        $this->assertSame(5, (int) $confirmed->lines->sum('qty_pieces'));

        $this->actingAs($this->manager)
            ->delete(route('gross.purchases.destroy', $confirmed))
            ->assertRedirect(route('gross.purchases.index'));

        $this->assertDatabaseMissing('gross_purchase_invoices', ['id' => $confirmed->id]);
        $this->assertSame(0, (int) GrossStockBalance::query()->where('item_id', $this->itemId)->value('qty_on_hand_pieces'));
    }

    public function test_manager_delete_confirmed_with_returns_unwinds_stock(): void
    {
        $service = app(PurchaseInvoiceService::class);
        $confirmed = $service->confirm($this->draft, $this->manager);
        $lineId = $confirmed->lines->first()->id;

        app(PurchaseReturnService::class)->create($this->manager, $confirmed->fresh(), [
            ['purchase_invoice_line_id' => $lineId, 'qty' => 1],
        ]);

        $this->assertSame(1, (int) GrossStockBalance::query()->where('item_id', $this->itemId)->value('qty_on_hand_pieces'));

        $this->actingAs($this->manager)
            ->put(route('gross.purchases.update', $confirmed->fresh()), $this->payload('No', 2, 10))
            ->assertRedirect()
            ->assertSessionHasErrors('lines');

        $this->actingAs($this->manager)
            ->delete(route('gross.purchases.destroy', $confirmed->fresh()))
            ->assertRedirect(route('gross.purchases.index'));

        $this->assertDatabaseMissing('gross_purchase_invoices', ['id' => $confirmed->id]);
        $this->assertSame(0, (int) GrossStockBalance::query()->where('item_id', $this->itemId)->value('qty_on_hand_pieces'));
    }

    public function test_accountant_can_still_create_and_confirm(): void
    {
        $this->actingAs($this->accountant->fresh())
            ->post(route('gross.purchases.store'), $this->payload('Walk-in', 1, 5))
            ->assertRedirect();

        $created = GrossPurchaseInvoice::query()->where('supplier_name', 'Walk-in')->firstOrFail();

        $this->actingAs($this->accountant->fresh())
            ->post(route('gross.purchases.confirm', $created))
            ->assertRedirect();

        $this->assertSame(GrossPurchaseInvoice::STATUS_CONFIRMED, $created->fresh()->status);
    }

    public function test_index_shows_edit_and_delete_for_confirmed(): void
    {
        $confirmed = app(PurchaseInvoiceService::class)->confirm($this->draft, $this->manager);

        $this->actingAs($this->manager)
            ->get(route('gross.purchases.index'))
            ->assertOk()
            ->assertSee(route('gross.purchases.edit', $confirmed), false)
            ->assertSee(route('gross.purchases.destroy', $confirmed), false);

        $this->actingAs($this->accountant->fresh())
            ->get(route('gross.purchases.index'))
            ->assertOk()
            ->assertDontSee(route('gross.purchases.edit', $confirmed), false);
    }

    /** @return array<string, mixed> */
    private function payload(string $supplier, int $qty, float $buy): array
    {
        return [
            'supplier_name' => $supplier,
            'supplier_phone' => '0100',
            'currency' => 'EGP',
            'discount_mode' => 'amount',
            'discount_amount' => 0,
            'lines' => [[
                'item_id' => $this->itemId,
                'qty' => $qty,
                'unit' => 'PIECE',
                'buy_price' => $buy,
                'sale_price' => 20,
                'discount_mode' => 'amount',
                'discount_amount' => 0,
            ]],
        ];
    }
}
