<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossReceiptPurpose;
use App\Models\Gross\GrossTreasuryAccount;
use App\Models\Gross\GrossTreasuryVoucher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptPurposeCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_crud_and_cannot_delete_in_use(): void
    {
        $manager = User::factory()->manager()->create();

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.receipt-purposes.index'))
            ->assertOk()
            ->assertSee('Receipt purposes', false)
            ->assertSee('Add receipt purpose', false);

        $this->actingAs($manager)
            ->post(route('gross.receipt-purposes.store'), [
                'name_ar' => 'رأس مال',
                'name_en' => 'Owner capital',
                'is_active' => 1,
            ])
            ->assertRedirect(route('gross.receipt-purposes.index'));

        $purpose = GrossReceiptPurpose::query()->where('name_en', 'Owner capital')->firstOrFail();

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.expense-types.index'))
            ->assertOk()
            ->assertSee('Receipt purposes', false)
            ->assertSee(route('gross.receipt-purposes.index'), false);

        $cash = GrossTreasuryAccount::query()->firstOrFail();
        $this->actingAs($manager)
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_RECEIPT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_OTHER,
                'receipt_purpose_id' => $purpose->id,
                'account_id' => $cash->id,
                'amount' => 25,
                'voucher_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->actingAs($manager)
            ->from(route('gross.receipt-purposes.index'))
            ->delete(route('gross.receipt-purposes.destroy', $purpose))
            ->assertRedirect()
            ->assertSessionHasErrors('receipt_purpose');

        $this->assertDatabaseHas('gross_receipt_purposes', ['id' => $purpose->id]);

        $this->actingAs($manager)
            ->get(route('gross.treasury.index', [
                'purpose' => 'OTHER',
                'receipt_purpose_id' => $purpose->id,
            ]))
            ->assertOk()
            ->assertSee('Owner capital', false);

        $this->actingAs($manager)
            ->put(route('gross.receipt-purposes.update', $purpose), [
                'name_ar' => 'رأس مال محدث',
                'name_en' => 'Owner capital updated',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertSame('Owner capital updated', $purpose->fresh()->name_en);
    }

    public function test_sales_forbidden_from_receipt_purposes(): void
    {
        $manager = User::factory()->manager()->create();
        $sales = User::factory()->sales()->create();

        $this->actingAs($sales)
            ->get(route('gross.receipt-purposes.index'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('gross.receipt-purposes.index'))
            ->assertOk();
    }

    public function test_inactive_receipt_purpose_rejected_on_store(): void
    {
        $manager = User::factory()->manager()->create();
        $purpose = GrossReceiptPurpose::factory()->create([
            'name_en' => 'Inactive purpose',
            'is_active' => false,
        ]);
        $cash = GrossTreasuryAccount::query()->firstOrFail();

        $this->actingAs($manager)
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_RECEIPT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_OTHER,
                'receipt_purpose_id' => $purpose->id,
                'account_id' => $cash->id,
                'amount' => 10,
                'voucher_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors();
    }
}
