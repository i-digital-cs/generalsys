<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correction: scheduled delivery is optional. Clear prior same-day backfill that
 * incorrectly assumed delivery = pickup day @ 08:00 Asia/Dubai.
 * Idempotent. Does not drop the column or touch financial tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gh_orders', 'scheduled_delivery_at')) {
            return;
        }

        DB::table('gh_orders')
            ->whereNotNull('scheduled_delivery_at')
            ->update(['scheduled_delivery_at' => null]);
    }

    public function down(): void
    {
        // Intentionally empty: prior same-day assumption must not be restored.
    }
};
