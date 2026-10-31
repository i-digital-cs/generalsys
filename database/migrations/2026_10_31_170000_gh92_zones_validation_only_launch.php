<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GH-9.2 — Owner-approved launch: zone minimum-order policy = VALIDATION_ONLY.
 *
 * Forward-only. Does not alter issued invoices, payments, or order financial snapshots.
 * Does not edit the historical GH-2 create migration; alters live table default + rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gh_zones')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        // Narrow data fix: only SURCHARGE → VALIDATION_ONLY (idempotent).
        DB::table('gh_zones')
            ->where('min_order_policy', 'SURCHARGE')
            ->update([
                'min_order_policy' => 'VALIDATION_ONLY',
                'updated_at' => now(),
            ]);

        // New inserts default to VALIDATION_ONLY (MySQL/MariaDB).
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE gh_zones MODIFY min_order_policy ENUM('VALIDATION_ONLY','SURCHARGE') NOT NULL DEFAULT 'VALIDATION_ONLY'");
        }
    }

    public function down(): void
    {
        // Non-reversible data policy by design (AGENTS.md forward-only). No-op.
    }
};
