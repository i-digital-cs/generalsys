<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Payroll\MonthCoverageException;
use App\Services\Payroll\PayrollGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Chunked draft payroll generation (≤ 100 employees) — Hostinger-safe (T-4.4 / Gate 4.11).
 */
class GeneratePayrollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  list<int>  $userIds
     */
    public function __construct(
        public readonly int $month,
        public readonly int $year,
        public readonly int $createdBy,
        public readonly array $userIds,
    ) {}

    public function handle(PayrollGenerator $generator): void
    {
        foreach ($this->userIds as $userId) {
            try {
                $user = User::query()->find($userId);
                if ($user === null) {
                    continue;
                }
                $generator->generateForUser($user, $this->month, $this->year, $this->createdBy);
            } catch (MonthCoverageException $e) {
                Log::warning($e->getMessage(), [
                    'user_id' => $e->userId,
                    'month' => $e->month,
                    'year' => $e->year,
                ]);
                // Continue other employees; coverage failures are surfaced on regenerate/sync path.
            }
        }
    }
}
