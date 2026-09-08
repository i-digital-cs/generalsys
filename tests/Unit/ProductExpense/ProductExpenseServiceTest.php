<?php

namespace Tests\Unit\ProductExpense;

use App\Models\Gross\GrossExpenseType;
use App\Models\Gross\GrossTreasuryAccount;
use App\Models\User;
use App\Services\ProductExpense\ProductExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductExpenseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaining_uses_treasury_expense_payments(): void
    {
        $manager = User::factory()->manager()->create();
        $petrol = GrossExpenseType::query()->where('name_en', 'Petrol')->firstOrFail();
        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();
        $cash->update(['opening_balance' => 10000]);
        $service = app(ProductExpenseService::class);

        $service->upsertBudget($manager, $petrol, 2026, 9, ['expected_amount' => 5000]);
        $service->addExpensePayment($manager, $petrol, [
            'account_id' => $cash->id,
            'amount' => 1200,
            'voucher_date' => '2026-09-05',
        ]);
        $service->addExpensePayment($manager, $petrol, [
            'account_id' => $cash->id,
            'amount' => 800,
            'voucher_date' => '2026-09-10',
        ]);

        $detail = $service->categoryMonthDetail($petrol, 2026, 9);
        $this->assertEqualsWithDelta(5000.0, $detail['expected'], 0.01);
        $this->assertEqualsWithDelta(2000.0, $detail['spent'], 0.01);
        $this->assertEqualsWithDelta(3000.0, $detail['remaining'], 0.01);
        $this->assertCount(2, $detail['vouchers']);

        $snap = $service->monthSnapshot(2026, 9);
        $this->assertEqualsWithDelta(5000.0, $snap['summary']['expected'], 0.01);
        $this->assertEqualsWithDelta(2000.0, $snap['summary']['spent'], 0.01);
        $this->assertEqualsWithDelta(3000.0, $snap['summary']['remaining'], 0.01);
    }

    public function test_overspend_allows_negative_remaining(): void
    {
        $manager = User::factory()->manager()->create();
        $rent = GrossExpenseType::query()->where('name_en', 'Rent')->firstOrFail();
        $cash = GrossTreasuryAccount::query()->where('kind', GrossTreasuryAccount::KIND_CASH)->firstOrFail();
        $cash->update(['opening_balance' => 5000]);
        $service = app(ProductExpenseService::class);

        $service->upsertBudget($manager, $rent, 2026, 9, ['expected_amount' => 1000]);
        $service->addExpensePayment($manager, $rent, [
            'account_id' => $cash->id,
            'amount' => 1500,
            'voucher_date' => '2026-09-01',
            'rent_period' => 'THIS_MONTH',
        ]);

        $detail = $service->categoryMonthDetail($rent, 2026, 9);
        $this->assertEqualsWithDelta(-500.0, $detail['remaining'], 0.01);
        $this->assertStringContainsString(__('product_expenses.rent_periods.THIS_MONTH'), (string) $detail['vouchers']->first()->notes);

        $snap = $service->monthSnapshot(2026, 9);
        $this->assertSame(1, $snap['summary']['over_budget_count']);
    }
}
