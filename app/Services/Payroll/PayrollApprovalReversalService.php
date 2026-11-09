<?php

namespace App\Services\Payroll;

use App\Models\PayrollRecord;
use App\Models\PayrollStatusAudit;
use App\Models\User;
use App\Support\ArabicNameNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Safe APPROVED → DRAFT reversal for a single employee payslip.
 *
 * Preserves salary/deduction amounts. Reverses advance side-effects from approve.
 * Blocks PAID and other unsafe financial dependencies.
 */
final class PayrollApprovalReversalService
{
    public function __construct(
        private readonly AdvanceService $advances,
    ) {}

    /**
     * @return Collection<int, User>
     */
    public function findEmployeesByName(string $name): Collection
    {
        $needle = trim($name);
        if ($needle === '') {
            return collect();
        }

        return User::query()
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en', 'email', 'role', 'status'])
            ->filter(function (User $user) use ($needle) {
                return ArabicNameNormalizer::contains((string) $user->name_ar, $needle)
                    || ArabicNameNormalizer::contains((string) $user->name_en, $needle);
            })
            ->values();
    }

    /**
     * @return Collection<int, PayrollRecord>
     */
    public function payrollsForEmployee(int $employeeId, ?int $month = null, ?int $year = null): Collection
    {
        $query = PayrollRecord::query()
            ->with(['user', 'adjustments'])
            ->where('user_id', $employeeId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('id');

        if ($month !== null) {
            $query->where('month', $month);
        }
        if ($year !== null) {
            $query->where('year', $year);
        }

        return $query->get();
    }

    /**
     * Resolve a unique payroll target or throw with a clear reason.
     *
     * @param  array{
     *     employee_id?: int|null,
     *     payroll_id?: int|null,
     *     name?: string|null,
     *     month?: int|null,
     *     year?: int|null
     * }  $criteria
     * @return array{
     *     employee: User,
     *     payroll: PayrollRecord,
     *     candidates_employees: Collection<int, User>,
     *     candidates_payrolls: Collection<int, PayrollRecord>
     * }
     */
    public function resolveUniqueTarget(array $criteria): array
    {
        $employeeId = isset($criteria['employee_id']) ? (int) $criteria['employee_id'] : null;
        $payrollId = isset($criteria['payroll_id']) ? (int) $criteria['payroll_id'] : null;
        $name = isset($criteria['name']) ? trim((string) $criteria['name']) : '';
        $month = isset($criteria['month']) ? (int) $criteria['month'] : null;
        $year = isset($criteria['year']) ? (int) $criteria['year'] : null;

        if ($payrollId) {
            $payroll = PayrollRecord::query()->with(['user', 'adjustments'])->find($payrollId);
            if ($payroll === null) {
                throw new InvalidArgumentException("Payroll record #{$payrollId} was not found.");
            }
            if ($employeeId && (int) $payroll->user_id !== $employeeId) {
                throw new InvalidArgumentException(
                    "Payroll #{$payrollId} does not belong to employee #{$employeeId}."
                );
            }
            $employee = $payroll->user;
            if ($employee === null) {
                throw new InvalidArgumentException("Employee for payroll #{$payrollId} was not found.");
            }

            return [
                'employee' => $employee,
                'payroll' => $payroll,
                'candidates_employees' => collect([$employee]),
                'candidates_payrolls' => collect([$payroll]),
            ];
        }

        $employees = collect();
        if ($employeeId) {
            $user = User::query()->find($employeeId);
            if ($user === null) {
                throw new InvalidArgumentException("Employee #{$employeeId} was not found.");
            }
            $employees = collect([$user]);
        } elseif ($name !== '') {
            $employees = $this->findEmployeesByName($name);
            if ($employees->isEmpty()) {
                throw new InvalidArgumentException("No employees matched name [{$name}].");
            }
            if ($employees->count() > 1) {
                throw new InvalidArgumentException(
                    'Ambiguous employee name — matches '.$employees->count()
                    .' users. Pass --employee-id to disambiguate.'
                );
            }
        } else {
            throw new InvalidArgumentException(
                'Provide --payroll-id and/or --employee-id (or --name) to select a target.'
            );
        }

        /** @var User $employee */
        $employee = $employees->first();
        $payrolls = $this->payrollsForEmployee((int) $employee->id, $month, $year);

        if ($payrolls->isEmpty()) {
            throw new InvalidArgumentException(
                'No payroll records found for employee #'.$employee->id
                .($month ? " month={$month}" : '')
                .($year ? " year={$year}" : '').'.'
            );
        }

        $approved = $payrolls->where('status', PayrollRecord::STATUS_APPROVED)->values();
        if ($approved->count() === 1) {
            return [
                'employee' => $employee,
                'payroll' => $approved->first(),
                'candidates_employees' => $employees,
                'candidates_payrolls' => $payrolls,
            ];
        }

        if ($approved->count() > 1) {
            throw new InvalidArgumentException(
                'Multiple APPROVED payroll records for this employee. Pass --payroll-id or --month/--year.'
            );
        }

        throw new InvalidArgumentException(
            'No APPROVED payroll record matched. Found '.$payrolls->count()
            .' payroll(s); none in APPROVED status for the selection.'
        );
    }

    /**
     * @return list<string>
     */
    public function safetyBlockers(PayrollRecord $payroll): array
    {
        $blockers = [];

        if ($payroll->status === PayrollRecord::STATUS_PAID) {
            $blockers[] = 'Payroll is PAID — refusing reversal (financially settled).';
        } elseif ($payroll->status === PayrollRecord::STATUS_DRAFT) {
            // Idempotent path — not a blocker for preview, handled in revert().
        } elseif ($payroll->status !== PayrollRecord::STATUS_APPROVED) {
            $blockers[] = 'Payroll status ['.$payroll->status.'] cannot be reverted to DRAFT.';
        }

        $laterSettled = PayrollRecord::query()
            ->where('user_id', $payroll->user_id)
            ->where('id', '!=', $payroll->id)
            ->whereIn('status', [PayrollRecord::STATUS_APPROVED, PayrollRecord::STATUS_PAID])
            ->where(function ($q) use ($payroll) {
                $q->where('year', '>', $payroll->year)
                    ->orWhere(function ($q2) use ($payroll) {
                        $q2->where('year', $payroll->year)
                            ->where('month', '>', $payroll->month);
                    });
            })
            ->orderBy('year')
            ->orderBy('month')
            ->get(['id', 'month', 'year', 'status']);

        if ($laterSettled->isNotEmpty()) {
            $sample = $laterSettled->map(
                fn (PayrollRecord $r) => "#{$r->id} {$r->year}-{$r->month} {$r->status}"
            )->implode(', ');
            $blockers[] = 'Later APPROVED/PAID payroll(s) exist for this employee (advance chain): '.$sample;
        }

        if ($payroll->status === PayrollRecord::STATUS_APPROVED) {
            try {
                $this->advances->plannedAdvanceRestoreAmounts($payroll);
            } catch (InvalidArgumentException $e) {
                $blockers[] = $e->getMessage();
            }
        }

        return $blockers;
    }

    /**
     * @return array{
     *     dry_run: bool,
     *     changed: bool,
     *     from_status: string,
     *     to_status: string,
     *     payroll_id: int,
     *     employee_id: int,
     *     net_pay: string,
     *     advance_restored: list<array{advance_id: int, restored: float, remaining_after: float}>,
     *     audit_id: int|null,
     *     message: string
     * }
     */
    public function revert(
        PayrollRecord $payroll,
        bool $execute,
        ?string $reason = null,
        ?int $actorId = null,
    ): array {
        $blockers = $this->safetyBlockers($payroll);
        if ($blockers !== []) {
            throw new InvalidArgumentException(implode(' ', $blockers));
        }

        if ($payroll->status === PayrollRecord::STATUS_DRAFT) {
            return [
                'dry_run' => ! $execute,
                'changed' => false,
                'from_status' => PayrollRecord::STATUS_DRAFT,
                'to_status' => PayrollRecord::STATUS_DRAFT,
                'payroll_id' => (int) $payroll->id,
                'employee_id' => (int) $payroll->user_id,
                'net_pay' => (string) $payroll->net_pay,
                'advance_restored' => [],
                'audit_id' => null,
                'message' => 'Idempotent: payroll is already DRAFT — no changes.',
            ];
        }

        $snapshot = [
            'net_pay' => (string) $payroll->net_pay,
            'gross_pay' => (string) $payroll->gross_pay,
            'total_deductions' => (string) $payroll->total_deductions,
            'advance_deductions' => (string) $payroll->advance_deductions,
            'status' => $payroll->status,
        ];

        if (! $execute) {
            return [
                'dry_run' => true,
                'changed' => false,
                'from_status' => PayrollRecord::STATUS_APPROVED,
                'to_status' => PayrollRecord::STATUS_DRAFT,
                'payroll_id' => (int) $payroll->id,
                'employee_id' => (int) $payroll->user_id,
                'net_pay' => (string) $payroll->net_pay,
                'advance_restored' => [],
                'audit_id' => null,
                'message' => 'Dry-run OK: would revert APPROVED → DRAFT and restore advance deductions if any.',
            ];
        }

        return DB::transaction(function () use ($payroll, $reason, $actorId, $snapshot) {
            /** @var PayrollRecord $locked */
            $locked = PayrollRecord::query()
                ->whereKey($payroll->id)
                ->lockForUpdate()
                ->firstOrFail();

            $blockers = $this->safetyBlockers($locked);
            if ($blockers !== []) {
                throw new InvalidArgumentException(implode(' ', $blockers));
            }

            if ($locked->status === PayrollRecord::STATUS_DRAFT) {
                return [
                    'dry_run' => false,
                    'changed' => false,
                    'from_status' => PayrollRecord::STATUS_DRAFT,
                    'to_status' => PayrollRecord::STATUS_DRAFT,
                    'payroll_id' => (int) $locked->id,
                    'employee_id' => (int) $locked->user_id,
                    'net_pay' => (string) $locked->net_pay,
                    'advance_restored' => [],
                    'audit_id' => null,
                    'message' => 'Idempotent: payroll is already DRAFT — no changes.',
                ];
            }

            $restored = $this->advances->reverseDeductionsForPayroll($locked);

            $locked->update(['status' => PayrollRecord::STATUS_DRAFT]);

            $moneyIntact = abs((float) $locked->fresh()->net_pay - (float) $snapshot['net_pay']) < 0.009
                && abs((float) $locked->fresh()->gross_pay - (float) $snapshot['gross_pay']) < 0.009
                && abs((float) $locked->fresh()->total_deductions - (float) $snapshot['total_deductions']) < 0.009
                && abs((float) $locked->fresh()->advance_deductions - (float) $snapshot['advance_deductions']) < 0.009;

            if (! $moneyIntact) {
                throw new InvalidArgumentException(
                    'Aborting: payroll money fields changed unexpectedly during reversal.'
                );
            }

            $audit = PayrollStatusAudit::query()->create([
                'payroll_record_id' => $locked->id,
                'user_id' => $locked->user_id,
                'actor_id' => $actorId,
                'from_status' => PayrollRecord::STATUS_APPROVED,
                'to_status' => PayrollRecord::STATUS_DRAFT,
                'action' => PayrollStatusAudit::ACTION_REVERT_APPROVED_TO_DRAFT,
                'reason' => $reason,
                'meta_json' => [
                    'snapshot' => $snapshot,
                    'advance_restored' => $restored,
                ],
                'recorded_at' => now(),
            ]);

            return [
                'dry_run' => false,
                'changed' => true,
                'from_status' => PayrollRecord::STATUS_APPROVED,
                'to_status' => PayrollRecord::STATUS_DRAFT,
                'payroll_id' => (int) $locked->id,
                'employee_id' => (int) $locked->user_id,
                'net_pay' => (string) $locked->fresh()->net_pay,
                'advance_restored' => $restored,
                'audit_id' => (int) $audit->id,
                'message' => 'Reverted APPROVED → DRAFT. Money fields preserved. Advances restored where applicable.',
            ];
        });
    }
}
