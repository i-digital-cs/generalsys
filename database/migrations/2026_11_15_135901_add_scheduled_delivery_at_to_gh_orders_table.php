<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add nullable scheduled_delivery_at for invoice-independent WORKING → DELIVERY promotion.
 *
 * Production safety:
 * - Schema only — does NOT invent synthetic delivery appointments for legacy rows.
 * - Column stays NULL for historical orders until a manager sets a real schedule.
 * - New orders require date+time via application validation (not a DB NOT NULL constraint).
 *
 * Same-day promotion uses picked_up_at + 2h; future-day uses 08:00 Asia/Dubai on this date
 * (see GhDeliveryEligibilityService). Invoice issuance does not gate promotion.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('gh_orders', 'scheduled_delivery_at')) {
            return;
        }

        Schema::table('gh_orders', function (Blueprint $table) {
            $table->timestamp('scheduled_delivery_at')->nullable()->after('pickup_ends_at');
            $table->index(['status', 'picked_up_at', 'scheduled_delivery_at'], 'idx_gh_order_delivery_promote');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('gh_orders', 'scheduled_delivery_at')) {
            return;
        }

        Schema::table('gh_orders', function (Blueprint $table) {
            $table->dropIndex('idx_gh_order_delivery_promote');
            $table->dropColumn('scheduled_delivery_at');
        });
    }
};
