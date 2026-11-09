<?php

namespace App\Services\Payroll;

use App\Models\Advance;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Salary advances (سلف) — CRUD + apply monthly deduction on payroll approve (T-4.8).
 */
class AdvanceService
{
    /**
     * @param  array{user_id?: int, status?: string}  $filters
     */
    public function listForAdmin(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Advance::query()
            ->with('user')
            ->orderByDesc('date_given')
            ->orderByDesc('id');

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['status']) && in_array($filters['status'], [
            Advance::STATUS_ACTIVE,
            Advance::STATUS_FULLY_DEDUCTED,
        ], true)) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate(min($perPage, 50));
    }

    public function listForEmployee(User $user): LengthAwarePaginator
    {
        return Advance::query()
            ->where('user_id', $user->id)
            ->orderByDesc('date_given')
            ->orderByDesc('id')
            ->paginate(25);
    }

    /**
     * @param  array{user_id: int, amount: float|string, date_given: string, deduction_amount_monthly: float|string}  $data
     */
    public function create(array $data): Advance
    {
        $amount = round((float) $data['amount'], 2);

        return Advance::query()->create([
            'user_id' => (int) $data['user_id'],
            'amount' => $amount,
            'date_given' => $data['date_given'],
            'deduction_amount_monthly' => round((float) $data['deduction_amount_monthly'], 2),
            'remaining_amount' => $amount,
            'status' => Advance::STATUS_ACTIVE,
        ]);
    }

    /**
     * @param  array{amount?: float|string, date_given?: string, deduction_amount_monthly?: float|string}  $data
     */
    public function update(Advance $advance, array $data): Advance
    {
        if ($advance->status !== Advance::STATUS_ACTIVE) {
            throw new InvalidArgumentException('Only ACTIVE advances can be updated.');
        }

        $amount = array_key_exists('amount', $data)
            ? round((float) $data['amount'], 2)
            : (float) $advance->amount;

        $monthly = array_key_exists('deduction_amount_monthly', $data)
            ? round((float) $data['deduction_amount_monthly'], 2)
            : (float) $advance->deduction_amount_monthly;

        $remaining = (float) $advance->remaining_amount;
        if (array_key_exists('amount', $data)) {
            // New loan principal: reset remaining when still fully outstanding.
            if (abs($remaining - (float) $advance->amount) < 0.001) {
                $remaining = $amount;
            } else {
                $remaining = min($remaining, $amount);
            }
        }

        $advance->update([
            'amount' => $amount,
            'date_given' => $data['date_given'] ?? $advance->date_given->toDateString(),
            'deduction_amount_monthly' => $monthly,
            'remaining_amount' => round($remaining, 2),
            'status' => $remaining <= 0 ? Advance::STATUS_FULLY_DEDUCTED : Advance::STATUS_ACTIVE,
        ]);

        return $advance->fresh('user');
    }

    /**
     * Decrement ACTIVE advances for the payslip employee (API §9 — on approval).
     */
    public function applyDeductionsForPayroll(PayrollRecord $record): void
    {
        DB::transaction(function () use ($record) {
            $advances = Advance::query()
                ->where('user_id', $record->user_id)
                ->where('status', Advance::STATUS_ACTIVE)
                ->where('remaining_amount', '>', 0)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            foreach ($advances as $advance) {
                $deduct = min(
                    (float) $advance->deduction_amount_monthly,
                    (float) $advance->remaining_amount,
                );
                if ($deduct <= 0) {
                    continue;
                }

                $remaining = round((float) $advance->remaining_amount - $deduct, 2);
                $advance->update([
                    'remaining_amount' => max(0, $remaining),
                    'status' => $remaining <= 0
                        ? Advance::STATUS_FULLY_DEDUCTED
                        : Advance::STATUS_ACTIVE,
                ]);
            }
        });
    }

    /**
     * @return list<float>
     *
     * @throws InvalidArgumentException
     */
    public function plannedAdvanceRestoreAmounts(PayrollRecord $record): array
    {
        $amounts = $record->adjustments()
            ->where('type', PayrollAdjustment::TYPE_ADVANCE)
            ->orderBy('id')
            ->pluck('amount')
            ->map(fn ($v) => round((float) $v, 2))
            ->values()
            ->all();

        $plannedTotal = round(array_sum($amounts), 2);
        $recorded = round((float) $record->advance_deductions, 2);

        if ($plannedTotal <= 0 && $recorded <= 0) {
            return [];
        }

        if ($plannedTotal <= 0 && $recorded > 0) {
            throw new InvalidArgumentException(
                'Payroll has advance_deductions but no ADVANCE adjustment lines to reverse safely.'
            );
        }

        if (abs($plannedTotal - $recorded) > 0.009) {
            throw new InvalidArgumentException(
                "Advance adjustment total ({$plannedTotal}) does not match payroll advance_deductions ({$recorded})."
            );
        }

        return $amounts;
    }

    /**
     * Restore advance remaining after an APPROVED→DRAFT reversal.
     *
     * Uses ADVANCE adjustment lines on the payslip (same order / amounts as
     * generate-time deduction planning) and credits advances by ascending id.
     *
     * @return list<array{advance_id: int, restored: float, remaining_after: float}>
     *
     * @throws InvalidArgumentException when restoration cannot be applied safely
     */
    public function reverseDeductionsForPayroll(PayrollRecord $record): array
    {
        return DB::transaction(function () use ($record) {
            $amounts = $this->plannedAdvanceRestoreAmounts($record);
            if ($amounts === []) {
                return [];
            }

            $advances = Advance::query()
                ->where('user_id', $record->user_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $restored = [];
            $cursor = 0;

            foreach ($amounts as $credit) {
                if ($credit <= 0) {
                    continue;
                }

                $placed = false;
                while ($cursor < $advances->count()) {
                    /** @var Advance $advance */
                    $advance = $advances[$cursor];
                    $newRemaining = round((float) $advance->remaining_amount + $credit, 2);
                    if ($newRemaining <= round((float) $advance->amount, 2) + 0.009) {
                        $advance->update([
                            'remaining_amount' => min($newRemaining, round((float) $advance->amount, 2)),
                            'status' => Advance::STATUS_ACTIVE,
                        ]);
                        $restored[] = [
                            'advance_id' => (int) $advance->id,
                            'restored' => $credit,
                            'remaining_after' => (float) $advance->fresh()->remaining_amount,
                        ];
                        $cursor++;
                        $placed = true;
                        break;
                    }
                    $cursor++;
                }

                if (! $placed) {
                    throw new InvalidArgumentException(
                        'Cannot safely restore advance deduction of '.$credit.' — no matching advance room.'
                    );
                }
            }

            return $restored;
        });
    }
}
