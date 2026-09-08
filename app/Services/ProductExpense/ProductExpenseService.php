<?php

namespace App\Services\ProductExpense;

use App\Models\Gross\GrossExpenseType;
use App\Models\Gross\GrossTreasuryVoucher;
use App\Models\ProductExpense\ProductExpenseMonthBudget;
use App\Models\User;
use App\Services\Admin\SettingService;
use App\Services\Gross\TreasuryService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Mohra Product expected-vs-actual expenses.
 * Categories + actual spends = Gross expense types + treasury PAYMENT/EXPENSE (إذن صرف).
 * Only monthly expected budgets are stored separately.
 */
class ProductExpenseService
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly TreasuryService $treasury,
    ) {}

    /**
     * @return array{
     *     year: int,
     *     month: int,
     *     month_label: string,
     *     currency: string,
     *     summary: array{expected: float, spent: float, remaining: float, over_budget_count: int},
     *     categories: list<array{id: int, name: string, expected: float, spent: float, remaining: float, over: bool, supports_rent_period: bool}>
     * }
     */
    public function monthSnapshot(?int $year = null, ?int $month = null): array
    {
        [$year, $month] = $this->resolveYearMonth($year, $month);
        $currency = $this->settings->get(SettingService::KEY_DEFAULT_CURRENCY, 'EGP') ?: 'EGP';
        $locale = app()->getLocale();

        $types = GrossExpenseType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();

        $budgets = ProductExpenseMonthBudget::query()
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('expense_type_id');

        $spentByType = $this->spentByExpenseType($year, $month);

        $rows = [];
        $totalExpected = 0.0;
        $totalSpent = 0.0;
        $overBudgetCount = 0;

        foreach ($types as $type) {
            $expected = (float) ($budgets->get($type->id)?->expected_amount ?? 0);
            $spent = (float) ($spentByType->get($type->id) ?? 0);
            $remaining = round($expected - $spent, 2);
            $over = $spent > $expected;

            if ($over) {
                $overBudgetCount++;
            }

            $totalExpected += $expected;
            $totalSpent += $spent;

            $rows[] = [
                'id' => $type->id,
                'name' => $type->localizedName($locale),
                'expected' => round($expected, 2),
                'spent' => round($spent, 2),
                'remaining' => $remaining,
                'over' => $over,
                'supports_rent_period' => (bool) $type->supports_rent_period,
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'month_label' => Carbon::create($year, $month, 1)->locale($locale)->translatedFormat('F Y'),
            'currency' => $currency,
            'summary' => [
                'expected' => round($totalExpected, 2),
                'spent' => round($totalSpent, 2),
                'remaining' => round($totalExpected - $totalSpent, 2),
                'over_budget_count' => $overBudgetCount,
            ],
            'categories' => $rows,
        ];
    }

    /**
     * @return array{
     *     expenseType: GrossExpenseType,
     *     year: int,
     *     month: int,
     *     month_label: string,
     *     currency: string,
     *     expected: float,
     *     spent: float,
     *     remaining: float,
     *     budget: ?ProductExpenseMonthBudget,
     *     vouchers: Collection<int, GrossTreasuryVoucher>,
     *     formula: string,
     *     accountsWithBalances: Collection
     * }
     */
    public function categoryMonthDetail(GrossExpenseType $expenseType, ?int $year = null, ?int $month = null): array
    {
        [$year, $month] = $this->resolveYearMonth($year, $month);
        $currency = $this->settings->get(SettingService::KEY_DEFAULT_CURRENCY, 'EGP') ?: 'EGP';
        $locale = app()->getLocale();

        $budget = ProductExpenseMonthBudget::query()
            ->where('expense_type_id', $expenseType->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $vouchers = GrossTreasuryVoucher::query()
            ->with(['account', 'creator:id,name_ar,name_en'])
            ->where('type', GrossTreasuryVoucher::TYPE_PAYMENT)
            ->where('purpose', GrossTreasuryVoucher::PURPOSE_EXPENSE)
            ->where('expense_type_id', $expenseType->id)
            ->whereYear('voucher_date', $year)
            ->whereMonth('voucher_date', $month)
            ->orderByDesc('voucher_date')
            ->orderByDesc('id')
            ->get();

        $expected = (float) ($budget?->expected_amount ?? 0);
        $spent = round((float) $vouchers->sum('amount'), 2);
        $remaining = round($expected - $spent, 2);

        return [
            'expenseType' => $expenseType,
            'year' => $year,
            'month' => $month,
            'month_label' => Carbon::create($year, $month, 1)->locale($locale)->translatedFormat('F Y'),
            'currency' => $currency,
            'expected' => $expected,
            'spent' => $spent,
            'remaining' => $remaining,
            'budget' => $budget,
            'vouchers' => $vouchers,
            'formula' => 'remaining = expected − SUM(إذن صرف EXPENSE vouchers)',
            'accountsWithBalances' => $this->treasury->accountsWithBalances(),
        ];
    }

    /**
     * @param  array{expected_amount: float|int|string, notes?: ?string}  $data
     */
    public function upsertBudget(User $actor, GrossExpenseType $expenseType, int $year, int $month, array $data): ProductExpenseMonthBudget
    {
        $this->assertValidYearMonth($year, $month);

        return ProductExpenseMonthBudget::query()->updateOrCreate(
            [
                'expense_type_id' => $expenseType->id,
                'year' => $year,
                'month' => $month,
            ],
            [
                'expected_amount' => round((float) $data['expected_amount'], 2),
                'currency' => $this->settings->get(SettingService::KEY_DEFAULT_CURRENCY, 'EGP') ?: 'EGP',
                'notes' => isset($data['notes']) ? trim((string) $data['notes']) ?: null : null,
                'created_by' => $actor->id,
            ],
        );
    }

    /**
     * Posts the same Gross treasury PAYMENT + EXPENSE voucher (إذن صرف).
     *
     * @param  array{account_id: int, amount: float|int|string, voucher_date: string, rent_period?: ?string, notes?: ?string}  $data
     */
    public function addExpensePayment(User $actor, GrossExpenseType $expenseType, array $data): GrossTreasuryVoucher
    {
        if (! $expenseType->isActive()) {
            throw ValidationException::withMessages([
                'expense_type_id' => __('product_expenses.errors.type_inactive'),
            ]);
        }

        $notes = isset($data['notes']) ? trim((string) $data['notes']) : '';
        $rentPeriod = $data['rent_period'] ?? null;
        if ($rentPeriod && $expenseType->supports_rent_period) {
            $label = __('product_expenses.rent_periods.'.$rentPeriod);
            $notes = trim($label.($notes !== '' ? ' — '.$notes : ''));
        }

        return $this->treasury->createPayment($actor, [
            'purpose' => GrossTreasuryVoucher::PURPOSE_EXPENSE,
            'expense_type_id' => $expenseType->id,
            'account_id' => (int) $data['account_id'],
            'amount' => $data['amount'],
            'voucher_date' => $data['voucher_date'],
            'notes' => $notes !== '' ? $notes : null,
        ]);
    }

    /**
     * @return Collection<int, float|string>
     */
    private function spentByExpenseType(int $year, int $month): Collection
    {
        return GrossTreasuryVoucher::query()
            ->selectRaw('expense_type_id, COALESCE(SUM(amount), 0) as spent')
            ->where('type', GrossTreasuryVoucher::TYPE_PAYMENT)
            ->where('purpose', GrossTreasuryVoucher::PURPOSE_EXPENSE)
            ->whereNotNull('expense_type_id')
            ->whereYear('voucher_date', $year)
            ->whereMonth('voucher_date', $month)
            ->groupBy('expense_type_id')
            ->pluck('spent', 'expense_type_id');
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolveYearMonth(?int $year, ?int $month): array
    {
        $now = now();
        $year ??= (int) $now->year;
        $month ??= (int) $now->month;
        $this->assertValidYearMonth($year, $month);

        return [$year, $month];
    }

    private function assertValidYearMonth(int $year, int $month): void
    {
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            throw ValidationException::withMessages([
                'month' => __('product_expenses.errors.invalid_month'),
            ]);
        }
    }
}
