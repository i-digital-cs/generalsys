<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop legacy payroll.multiplier.* settings — defaults live in config/payroll.php;
 * per-employee OT multiplier / extra pay are edited on each payroll draft.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->where('key', 'like', 'payroll.multiplier.%')
            ->delete();
    }

    public function down(): void
    {
        // Defaults remain in config/payroll.php — no need to restore DB rows.
    }
};
