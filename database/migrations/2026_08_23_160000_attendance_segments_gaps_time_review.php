<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-9.1 — Multi-punch same shift: segments + gaps + credited hours / overtime review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendance')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->dateTime('clock_in');
            $table->dateTime('clock_out')->nullable();
            $table->decimal('clock_in_lat', 10, 7)->nullable();
            $table->decimal('clock_in_lng', 10, 7)->nullable();
            $table->unsignedInteger('clock_in_accuracy_m')->nullable();
            $table->unsignedInteger('clock_in_distance_m')->nullable();
            $table->boolean('clock_in_outside_geofence')->default(false);
            $table->decimal('clock_out_lat', 10, 7)->nullable();
            $table->decimal('clock_out_lng', 10, 7)->nullable();
            $table->unsignedInteger('clock_out_accuracy_m')->nullable();
            $table->unsignedInteger('clock_out_distance_m')->nullable();
            $table->boolean('clock_out_outside_geofence')->default(false);
            $table->timestamps();

            $table->unique(['attendance_id', 'sequence'], 'uq_att_seg_att_seq');
            $table->index('attendance_id');
        });

        Schema::create('attendance_gaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendance')->cascadeOnDelete();
            $table->dateTime('gap_start');
            $table->dateTime('gap_end');
            $table->unsignedInteger('minutes');
            $table->enum('tl_recommendation', ['PENDING', 'APPROVE', 'REJECT'])->default('PENDING');
            $table->foreignId('tl_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('tl_decided_at')->nullable();
            $table->string('tl_note', 500)->nullable();
            $table->enum('admin_decision', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->foreignId('admin_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('admin_decided_at')->nullable();
            $table->string('admin_note', 500)->nullable();
            $table->timestamps();

            $table->index(['attendance_id', 'admin_decision'], 'idx_att_gaps_att_admin');
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->decimal('raw_actual_hours', 6, 2)->default(0)->after('actual_hours');
            $table->decimal('credited_hours', 6, 2)->nullable()->after('raw_actual_hours');
            $table->unsignedInteger('adjusted_late_minutes')->nullable()->after('late_minutes');
            $table->unsignedInteger('recommended_overtime_minutes')->default(0)->after('early_departure_minutes');
            $table->enum('overtime_tl_recommendation', ['NONE', 'RECOMMEND', 'NO_OVERTIME'])->default('NONE')->after('recommended_overtime_minutes');
            $table->foreignId('overtime_tl_by')->nullable()->after('overtime_tl_recommendation')->constrained('users')->nullOnDelete();
            $table->timestamp('overtime_tl_at')->nullable()->after('overtime_tl_by');
            $table->enum('overtime_admin_decision', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING')->after('overtime_tl_at');
            $table->foreignId('overtime_admin_by')->nullable()->after('overtime_admin_decision')->constrained('users')->nullOnDelete();
            $table->timestamp('overtime_admin_at')->nullable()->after('overtime_admin_by');
        });

        if (Schema::hasTable('attendance_photos') && ! Schema::hasColumn('attendance_photos', 'attendance_segment_id')) {
            Schema::table('attendance_photos', function (Blueprint $table) {
                $table->foreignId('attendance_segment_id')->nullable()->after('attendance_id')
                    ->constrained('attendance_segments')->nullOnDelete();
            });
        }

        $this->backfillSegmentsFromAttendance();
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendance_photos', 'attendance_segment_id')) {
            Schema::table('attendance_photos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('attendance_segment_id');
            });
        }

        Schema::table('attendance', function (Blueprint $table) {
            $table->dropConstrainedForeignId('overtime_admin_by');
            $table->dropConstrainedForeignId('overtime_tl_by');
            $table->dropColumn([
                'raw_actual_hours',
                'credited_hours',
                'adjusted_late_minutes',
                'recommended_overtime_minutes',
                'overtime_tl_recommendation',
                'overtime_tl_at',
                'overtime_admin_decision',
                'overtime_admin_at',
            ]);
        });

        Schema::dropIfExists('attendance_gaps');
        Schema::dropIfExists('attendance_segments');
    }

    private function backfillSegmentsFromAttendance(): void
    {
        if (! Schema::hasTable('attendance')) {
            return;
        }

        $rows = DB::table('attendance')->whereNotNull('clock_in')->orderBy('id')->get();
        $now = now();

        foreach ($rows as $row) {
            $exists = DB::table('attendance_segments')->where('attendance_id', $row->id)->exists();
            if ($exists) {
                continue;
            }

            DB::table('attendance_segments')->insert([
                'attendance_id' => $row->id,
                'sequence' => 1,
                'clock_in' => $row->clock_in,
                'clock_out' => $row->clock_out,
                'clock_in_lat' => $row->clock_in_lat ?? null,
                'clock_in_lng' => $row->clock_in_lng ?? null,
                'clock_in_accuracy_m' => $row->clock_in_accuracy_m ?? null,
                'clock_in_distance_m' => $row->clock_in_distance_m ?? null,
                'clock_in_outside_geofence' => (bool) ($row->clock_in_outside_geofence ?? false),
                'clock_out_lat' => $row->clock_out_lat ?? null,
                'clock_out_lng' => $row->clock_out_lng ?? null,
                'clock_out_accuracy_m' => $row->clock_out_accuracy_m ?? null,
                'clock_out_distance_m' => $row->clock_out_distance_m ?? null,
                'clock_out_outside_geofence' => (bool) ($row->clock_out_outside_geofence ?? false),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('attendance')->where('id', $row->id)->update([
                'raw_actual_hours' => $row->actual_hours ?? 0,
                'credited_hours' => $row->actual_hours ?? 0,
            ]);
        }
    }
};
