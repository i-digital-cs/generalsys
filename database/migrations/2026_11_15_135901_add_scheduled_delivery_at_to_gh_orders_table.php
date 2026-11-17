<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled delivery instant for invoice-independent WORKING → DELIVERY promotion.
 * Same-day orders use picked_up_at + 2h; future-day orders use 08:00 Asia/Dubai on this date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gh_orders', function (Blueprint $table) {
            $table->timestamp('scheduled_delivery_at')->nullable()->after('pickup_ends_at');
            $table->index(['status', 'picked_up_at', 'scheduled_delivery_at'], 'idx_gh_order_delivery_promote');
        });

        // Backfill: default scheduled delivery to the local pickup calendar day at 08:00 Dubai (stored UTC).
        if (Schema::hasColumn('gh_orders', 'scheduled_delivery_at')) {
            $rows = DB::table('gh_orders')->select(['id', 'pickup_starts_at', 'timezone'])->orderBy('id')->get();
            foreach ($rows as $row) {
                if ($row->pickup_starts_at === null) {
                    continue;
                }
                try {
                    $tz = 'Asia/Dubai';
                    $pickupLocal = Carbon::parse($row->pickup_starts_at, 'UTC')->timezone($tz);
                    $deliveryUtc = $pickupLocal->copy()->startOfDay()->setTime(8, 0)->utc();
                    DB::table('gh_orders')->where('id', $row->id)->update([
                        'scheduled_delivery_at' => $deliveryUtc->format('Y-m-d H:i:s'),
                    ]);
                } catch (Throwable) {
                    // Leave null; eligibility falls back to pickup day.
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('gh_orders', function (Blueprint $table) {
            $table->dropIndex('idx_gh_order_delivery_promote');
            $table->dropColumn('scheduled_delivery_at');
        });
    }
};
