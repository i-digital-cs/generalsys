<?php

use App\Services\GeneralHouse\Orders\GhUtcTimestampRepairService;
use Illuminate\Database\Migrations\Migration;

/**
 * Data-only: reinterpret legacy GH TIMESTAMP digits as the wall-clock zone they
 * were written in, then store canonical UTC. Idempotent via gh_sequences marker.
 *
 * Does not alter migration history for GH-1..GH-4. Does not touch Attendance/Payroll/Gross.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(GhUtcTimestampRepairService::class)->apply();
    }

    public function down(): void
    {
        // Irreversible without restoring the pre-repair backup.
    }
};
