<?php

namespace App\Console\Commands;

use App\Models\PayrollRecord;
use App\Models\User;
use App\Services\Payroll\PayrollApprovalReversalService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Revert a single payroll record APPROVED → DRAFT (default dry-run).
 *
 * Identifies employee/payroll at runtime — never hardcodes production IDs.
 */
class RevertApprovedPayrollToDraftCommand extends Command
{
    protected $signature = 'payroll:revert-approved-to-draft
        {--employee-id= : Target employee user id}
        {--payroll-id= : Target payroll_records.id}
        {--name= : Arabic/English employee name search (normalized)}
        {--month= : Payroll month 1-12}
        {--year= : Payroll year}
        {--reason= : Audit reason (required with --execute)}
        {--actor-id= : Optional acting user id for audit}
        {--execute : Persist the reversal (default is read-only dry-run)}';

    protected $description = 'Safely revert one APPROVED payroll record to DRAFT (dry-run by default)';

    public function handle(PayrollApprovalReversalService $reversals): int
    {
        $execute = (bool) $this->option('execute');
        $reason = $this->option('reason');
        $reason = is_string($reason) ? trim($reason) : '';

        if ($execute && $reason === '') {
            $this->error('--reason is required when using --execute.');

            return self::FAILURE;
        }

        $month = $this->option('month');
        $year = $this->option('year');
        $actor = $this->option('actor-id');

        try {
            $resolved = $reversals->resolveUniqueTarget([
                'employee_id' => $this->option('employee-id') !== null && $this->option('employee-id') !== ''
                    ? (int) $this->option('employee-id')
                    : null,
                'payroll_id' => $this->option('payroll-id') !== null && $this->option('payroll-id') !== ''
                    ? (int) $this->option('payroll-id')
                    : null,
                'name' => $this->option('name'),
                'month' => $month !== null && $month !== '' ? (int) $month : null,
                'year' => $year !== null && $year !== '' ? (int) $year : null,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            $this->printNameMatches($reversals);

            return self::FAILURE;
        }

        /** @var User $employee */
        $employee = $resolved['employee'];
        /** @var PayrollRecord $payroll */
        $payroll = $resolved['payroll'];

        $this->info('Mode: '.($execute ? 'EXECUTE' : 'DRY-RUN (no writes)'));
        $this->table(
            ['Field', 'Value'],
            [
                ['employee_id', $employee->id],
                ['name_ar', $employee->name_ar],
                ['name_en', $employee->name_en],
                ['payroll_id', $payroll->id],
                ['period', sprintf('%04d-%02d', $payroll->year, $payroll->month)],
                ['status', $payroll->status],
                ['currency', $payroll->currency],
                ['net_pay', $payroll->net_pay],
                ['gross_pay', $payroll->gross_pay],
                ['total_deductions', $payroll->total_deductions],
                ['advance_deductions', $payroll->advance_deductions],
            ]
        );

        if ($resolved['candidates_payrolls']->count() > 1) {
            $this->line('Other payroll rows for this employee (unchanged unless selected):');
            $this->table(
                ['id', 'year', 'month', 'status', 'net_pay'],
                $resolved['candidates_payrolls']->map(fn (PayrollRecord $r) => [
                    $r->id,
                    $r->year,
                    $r->month,
                    $r->status,
                    $r->net_pay,
                ])->all()
            );
        }

        $blockers = $reversals->safetyBlockers($payroll);
        if ($blockers !== [] && $payroll->status !== PayrollRecord::STATUS_DRAFT) {
            foreach ($blockers as $blocker) {
                $this->error($blocker);
            }

            return self::FAILURE;
        }

        try {
            $result = $reversals->revert(
                $payroll,
                $execute,
                $reason !== '' ? $reason : null,
                $actor !== null && $actor !== '' ? (int) $actor : null,
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if ($result['dry_run']) {
            $this->warn('Dry-run only. Re-run with --execute --reason="..." to persist.');
        } elseif ($result['changed']) {
            $this->info('Reversal committed.');
        } else {
            $this->info($result['message']);
        }

        return self::SUCCESS;
    }

    private function printNameMatches(PayrollApprovalReversalService $reversals): void
    {
        $name = $this->option('name');
        if (! is_string($name) || trim($name) === '') {
            return;
        }

        $matches = $reversals->findEmployeesByName($name);
        if ($matches->isEmpty()) {
            return;
        }

        $this->line('Matching employees:');
        $this->table(
            ['id', 'name_ar', 'name_en', 'email', 'role', 'status'],
            $matches->map(fn (User $u) => [
                $u->id,
                $u->name_ar,
                $u->name_en,
                $u->email,
                $u->role,
                $u->status,
            ])->all()
        );
    }
}
