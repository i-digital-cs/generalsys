<?php

namespace Tests\Feature\ProductExpense;

use App\Models\Gross\GrossExpenseType;
use App\Models\Gross\GrossTreasuryAccount;
use App\Models\Gross\GrossTreasuryVoucher;
use App\Models\User;
use App\Models\ValuationAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductExpenseModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_home_shows_expense_cards_for_manager(): void
    {
        $manager = User::factory()->manager()->create();

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('portal.product'))
            ->assertOk()
            ->assertSee(__('product_expenses.summary.title'), false)
            ->assertSee(__('product_expenses.summary.expected'), false)
            ->assertSee('Petrol', false)
            ->assertSee('Rent', false);
    }

    public function test_service_home_does_not_show_expense_cards(): void
    {
        $manager = User::factory()->manager()->create();

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('portal.service'))
            ->assertOk()
            ->assertDontSee(__('product_expenses.summary.title'), false);
    }

    public function test_assigned_user_can_view_but_not_write(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        ValuationAssignment::query()->create([
            'user_id' => $employee->id,
            'assigned_by' => $manager->id,
            'module' => 'product',
        ]);
        $petrol = GrossExpenseType::query()->where('name_en', 'Petrol')->firstOrFail();

        $this->actingAs($employee)
            ->get(route('product.expenses.show', $petrol))
            ->assertOk();

        $this->actingAs($employee)
            ->put(route('product.expenses.budget', $petrol), [
                'year' => 2026,
                'month' => 9,
                'expected_amount' => 5000,
            ])
            ->assertForbidden();
    }

    public function test_manager_budget_and_treasury_payment_update_remaining(): void
    {
        $manager = User::factory()->manager()->create();
        $petrol = GrossExpenseType::query()->where('name_en', 'Petrol')->firstOrFail();
        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();
        $cash->update(['opening_balance' => 10000]);

        $this->actingAs($manager)
            ->put(route('product.expenses.budget', $petrol), [
                'year' => 2026,
                'month' => 9,
                'expected_amount' => 5000,
            ])
            ->assertRedirect();

        $this->actingAs($manager)
            ->post(route('product.expenses.payments.store', $petrol), [
                'account_id' => $cash->id,
                'amount' => 1500,
                'voucher_date' => '2026-09-08',
                'notes' => 'First fill',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('gross_treasury_vouchers', [
            'type' => GrossTreasuryVoucher::TYPE_PAYMENT,
            'purpose' => GrossTreasuryVoucher::PURPOSE_EXPENSE,
            'expense_type_id' => $petrol->id,
            'amount' => 1500,
        ]);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('product.expenses.show', ['expenseType' => $petrol, 'year' => 2026, 'month' => 9]))
            ->assertOk()
            ->assertSee(__('product_expenses.formula_title'), false)
            ->assertSee('First fill', false)
            ->assertSee('3,500.00', false);
    }

    public function test_treasury_expense_payment_counts_on_dashboard(): void
    {
        $manager = User::factory()->manager()->create();
        $electricity = GrossExpenseType::query()->where('name_en', 'Electricity')->firstOrFail();
        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();
        $cash->update(['opening_balance' => 5000]);

        $this->actingAs($manager)
            ->post(route('gross.treasury.store'), [
                'type' => GrossTreasuryVoucher::TYPE_PAYMENT,
                'purpose' => GrossTreasuryVoucher::PURPOSE_EXPENSE,
                'expense_type_id' => $electricity->id,
                'account_id' => $cash->id,
                'amount' => 250,
                'voucher_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('portal.product'))
            ->assertOk()
            ->assertSee('Electricity', false)
            ->assertSee('250.00', false);
    }
}
