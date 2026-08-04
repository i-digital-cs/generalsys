<?php

namespace App\Services\Payroll;

use App\Models\Advance;
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
}
