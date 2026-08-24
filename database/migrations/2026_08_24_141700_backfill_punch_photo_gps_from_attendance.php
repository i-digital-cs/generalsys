<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copy punch GPS from attendance onto CLOCK_IN / CLOCK_OUT photos that were
 * saved without per-photo lat/lng (location lived only on the attendance row).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_photos') || ! Schema::hasColumn('attendance_photos', 'lat')) {
            return;
        }

        DB::table('attendance_photos')
            ->whereIn('kind', ['CLOCK_IN', 'CLOCK_OUT'])
            ->whereNull('lat')
            ->orderBy('id')
            ->chunkById(100, function ($photos): void {
                $attendanceIds = $photos->pluck('attendance_id')->unique()->all();
                $attendances = DB::table('attendance')
                    ->whereIn('id', $attendanceIds)
                    ->get()
                    ->keyBy('id');

                foreach ($photos as $photo) {
                    $attendance = $attendances->get($photo->attendance_id);
                    if ($attendance === null) {
                        continue;
                    }

                    $payload = $photo->kind === 'CLOCK_IN'
                        ? [
                            'lat' => $attendance->clock_in_lat,
                            'lng' => $attendance->clock_in_lng,
                            'accuracy_m' => $attendance->clock_in_accuracy_m ?? null,
                            'distance_m' => $attendance->clock_in_distance_m ?? null,
                            'outside_geofence' => (bool) ($attendance->clock_in_outside_geofence ?? false),
                        ]
                        : [
                            'lat' => $attendance->clock_out_lat,
                            'lng' => $attendance->clock_out_lng,
                            'accuracy_m' => $attendance->clock_out_accuracy_m ?? null,
                            'distance_m' => $attendance->clock_out_distance_m ?? null,
                            'outside_geofence' => (bool) ($attendance->clock_out_outside_geofence ?? false),
                        ];

                    if ($payload['lat'] === null || $payload['lng'] === null) {
                        continue;
                    }

                    DB::table('attendance_photos')->where('id', $photo->id)->update($payload);
                }
            });
    }

    public function down(): void
    {
        // Data backfill — irreversible without knowing which rows were empty before.
    }
};
