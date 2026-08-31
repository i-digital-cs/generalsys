<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossExpenseType;
use App\Models\Gross\GrossTreasuryAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseTypeCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_add_and_cannot_delete_in_use(): void
    {
        $manager = User::factory()->manager()->create();

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.expense-types.index'))
            ->assertOk()
            ->assertSee('Expense types', false)
            ->assertSee('Electricity', false)
            ->assertSee('Total types', false)
            ->assertSee('gross-expense-type-edit-modal', false)
            ->assertSee('Add expense type', false);

        $this->actingAs($manager)
            ->get(route('gross.expense-types.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('Electricity', false);

        $this->actingAs($manager)
            ->post(route('gross.expense-types.store'), [
                'name_ar' => 'ماء',
                'name_en' => 'Water',
                'is_active' => 1,
            ])
            ->assertRedirect(route('gross.expense-types.index'));

        $water = GrossExpenseType::query()->where('name_en', 'Water')->firstOrFail();

        $cash = GrossTreasuryAccount::query()->firstOrFail();
        $this->actingAs($manager)
            ->post(route('gross.treasury.store'), [
                'type' => 'PAYMENT',
                'purpose' => 'EXPENSE',
                'expense_type_id' => $water->id,
                'account_id' => $cash->id,
                'amount' => 5,
                'voucher_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('amount');

        $cash->update(['opening_balance' => 100]);

        $this->actingAs($manager)
            ->post(route('gross.treasury.store'), [
                'type' => 'PAYMENT',
                'purpose' => 'EXPENSE',
                'expense_type_id' => $water->id,
                'account_id' => $cash->id,
                'amount' => 5,
                'voucher_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->actingAs($manager)
            ->from(route('gross.expense-types.index'))
            ->delete(route('gross.expense-types.destroy', $water))
            ->assertRedirect()
            ->assertSessionHasErrors('expense_type');

        $this->assertDatabaseHas('gross_expense_types', ['id' => $water->id]);

        $this->actingAs($manager)
            ->get(route('gross.treasury.index', [
                'purpose' => 'EXPENSE',
                'expense_type_id' => $water->id,
            ]))
            ->assertOk()
            ->assertSee('Water', false);
    }
}
