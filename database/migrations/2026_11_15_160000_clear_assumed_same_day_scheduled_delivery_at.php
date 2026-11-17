<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correction for environments that ran an earlier revision of
 * 2026_11_15_135901 which backfilled every order to pickup-day 08:00 Asia/Dubai.
 *
 * Safety rules:
 * - Clears ONLY values that exactly match that synthetic pattern.
 * - Never clears legitimate manager/API appointments (other date/times).
 * - Idempotent. Does not drop the column. Does not touch financial tables.
 * - On a fresh upgrade where 135901 no longer backfills, this updates 0 rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gh_orders', 'scheduled_delivery_at')) {
            return;
        }

        $rows = DB::table('gh_orders')
            ->whereNotNull('scheduled_delivery_at')
            ->whereNotNull('pickup_starts_at')
            ->select(['id', 'pickup_starts_at', 'scheduled_delivery_at'])
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            try {
                if ($this->isSyntheticPickupDayEightAm((string) $row->pickup_starts_at, (string) $row->scheduled_delivery_at)) {
                    DB::table('gh_orders')->where('id', $row->id)->update([
                        'scheduled_delivery_at' => null,
                    ]);
                }
            } catch (Throwable) {
                // Leave the row untouched — never invent or wipe on parse failure.
            }
        }
    }

    public function down(): void
    {
        // Intentionally empty: synthetic same-day 08:00 assumption must not be restored.
    }

    /**
     * True when scheduled_delivery_at equals pickup local calendar day @ 08:00 Asia/Dubai (UTC).
     */
    private function isSyntheticPickupDayEightAm(string $pickupStartsAtUtc, string $scheduledDeliveryAtUtc): bool
    {
        $tz = 'Asia/Dubai';
        $pickupLocal = Carbon::parse($pickupStartsAtUtc, 'UTC')->timezone($tz);
        $syntheticUtc = $pickupLocal->copy()->startOfDay()->setTime(8, 0)->utc()->format('Y-m-d H:i:s');
        $actualUtc = Carbon::parse($scheduledDeliveryAtUtc, 'UTC')->format('Y-m-d H:i:s');

        return $actualUtc === $syntheticUtc;
    }
};
