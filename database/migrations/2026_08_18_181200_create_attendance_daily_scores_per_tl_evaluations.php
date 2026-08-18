<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-8.5 — per-TL daily scores; evaluations unique (user, TL, month, year).
 * Attendance % stays on attendance; the four TL metrics move off attendance.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_daily_scores')) {
            Schema::create('attendance_daily_scores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attendance_id')->constrained('attendance')->cascadeOnDelete();
                $table->foreignId('team_leader_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('scored_by')->constrained('users')->cascadeOnDelete();
                $table->decimal('performance_score', 5, 2);
                $table->decimal('behavior_score', 5, 2);
                $table->decimal('productivity_score', 5, 2);
                $table->decimal('interest_return_score', 5, 2);
                $table->timestamps();

                $table->unique(['attendance_id', 'team_leader_id'], 'uq_ads_attendance_tl');
                $table->index('team_leader_id', 'idx_ads_tl');
            });
        }

        if (Schema::hasColumn('attendance', 'performance_score')) {
            $this->backfillDailyScores();

            Schema::table('attendance', function (Blueprint $table) {
                $table->dropColumn([
                    'performance_score',
                    'behavior_score',
                    'productivity_score',
                    'interest_return_score',
                ]);
            });
        }

        // Add the new unique first so user_id still has a covering index for its FK.
        if (! Schema::hasIndex('evaluations', 'uq_eval_user_tl_month')) {
            Schema::table('evaluations', function (Blueprint $table) {
                $table->unique(['user_id', 'team_leader_id', 'month', 'year'], 'uq_eval_user_tl_month');
            });
        }

        if (Schema::hasIndex('evaluations', 'uq_eval_user_month')) {
            Schema::table('evaluations', function (Blueprint $table) {
                $table->dropUnique('uq_eval_user_month');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('evaluations', 'uq_eval_user_month')) {
            Schema::table('evaluations', function (Blueprint $table) {
                $table->unique(['user_id', 'month', 'year'], 'uq_eval_user_month');
            });
        }

        if (Schema::hasIndex('evaluations', 'uq_eval_user_tl_month')) {
            Schema::table('evaluations', function (Blueprint $table) {
                $table->dropUnique('uq_eval_user_tl_month');
            });
        }

        if (! Schema::hasColumn('attendance', 'performance_score')) {
            Schema::table('attendance', function (Blueprint $table) {
                $table->decimal('performance_score', 5, 2)->nullable();
                $table->decimal('behavior_score', 5, 2)->nullable();
                $table->decimal('productivity_score', 5, 2)->nullable();
                $table->decimal('interest_return_score', 5, 2)->nullable();
            });
        }

        Schema::dropIfExists('attendance_daily_scores');
    }

    private function backfillDailyScores(): void
    {
        $rows = DB::table('attendance')
            ->where(function ($query): void {
                $query->whereNotNull('performance_score')
                    ->orWhereNotNull('behavior_score')
                    ->orWhereNotNull('productivity_score')
                    ->orWhereNotNull('interest_return_score');
            })
            ->get(['id', 'user_id', 'performance_score', 'behavior_score', 'productivity_score', 'interest_return_score']);

        foreach ($rows as $row) {
            $leaderId = DB::table('employee_team_leaders')
                ->where('user_id', $row->user_id)
                ->orderBy('id')
                ->value('team_leader_id');

            if ($leaderId === null) {
                continue;
            }

            $exists = DB::table('attendance_daily_scores')
                ->where('attendance_id', $row->id)
                ->where('team_leader_id', $leaderId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('attendance_daily_scores')->insert([
                'attendance_id' => $row->id,
                'team_leader_id' => $leaderId,
                'scored_by' => $leaderId,
                'performance_score' => $row->performance_score ?? 0,
                'behavior_score' => $row->behavior_score ?? 0,
                'productivity_score' => $row->productivity_score ?? 0,
                'interest_return_score' => $row->interest_return_score ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
