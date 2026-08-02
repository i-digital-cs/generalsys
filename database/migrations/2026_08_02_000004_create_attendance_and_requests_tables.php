<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 foundation tables — docs/DATABASE_SCHEMA.md §7–§11, §17.
 */
return new class extends Migration
{
    public function up(): void
    {
        // §7 attendance
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->enum('shift_type', [
                'FULL_TIME', 'ONLINE_HALF', 'ONLINE_FULL', 'EXTRA_SHIFT', 'OVERTIME',
            ]);
            $table->boolean('is_friday_work')->default(false);
            $table->boolean('is_day_off_work')->default(false);
            $table->boolean('is_official_holiday_work')->default(false);
            $table->foreignId('assigned_by_tl_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('clock_in')->nullable();
            $table->dateTime('clock_out')->nullable();
            $table->decimal('planned_hours', 4, 2)->default(0);
            $table->decimal('actual_hours', 4, 2)->default(0);
            $table->enum('status', [
                'PRESENT', 'LATE', 'EARLY_LEAVE', 'REST_DAY', 'OFFICIAL_HOLIDAY',
                'EXCUSED_ABSENCE', 'UNEXCUSED_ABSENCE', 'VACATION',
            ])->default('PRESENT');
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_departure_minutes')->default(0);
            $table->text('daily_report')->nullable();
            $table->text('team_leader_response')->nullable();
            $table->decimal('performance_score', 5, 2)->nullable();
            $table->decimal('behavior_score', 5, 2)->nullable();
            $table->decimal('productivity_score', 5, 2)->nullable();
            $table->decimal('interest_return_score', 5, 2)->nullable();
            $table->decimal('attendance_score', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date', 'shift_type'], 'uq_att_user_date_shift');
            $table->index('date', 'idx_att_date');
            $table->index(['user_id', 'date'], 'idx_att_user_month');
            $table->index('status', 'idx_att_status');
        });

        // §8 permissions (leave / late requests — not ACL)
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['LEAVE_PERMISSION', 'LATE_PERMISSION']);
            $table->date('request_date');
            $table->time('from_time');
            $table->time('to_time');
            $table->text('reason')->nullable();
            $table->enum('status', ['PENDING', 'ACCEPTED', 'REJECTED', 'FORCED'])->default('PENDING');
            $table->foreignId('team_leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('penalty_amount', 12, 2)->default(0);
            $table->boolean('is_double_penalty')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'request_date'], 'idx_perm_user_date');
            $table->index(['team_leader_id', 'status'], 'idx_perm_tl_status');
        });

        // §9 vacations
        Schema::create('vacations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['ANNUAL', 'SICK', 'EMERGENCY']);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days_requested', 4, 1);
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->foreignId('team_leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'idx_vac_user_status');
            $table->index(['start_date', 'end_date'], 'idx_vac_dates');
        });

        // §10 vacation_balances
        Schema::create('vacation_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('accrued_days', 5, 1)->default(0);
            $table->decimal('used_days', 5, 1)->default(0);
            $table->decimal('pending_days', 5, 1)->default(0);
            $table->decimal('remaining_days', 5, 1)->default(0);
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'year'], 'uq_vb_user_year');
        });

        // §11 vacation_credit_logs (created_at only — append-only audit)
        Schema::create('vacation_credit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacation_balance_id')->constrained('vacation_balances')->cascadeOnDelete();
            $table->foreignId('added_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount_days', 5, 1);
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('vacation_balance_id', 'idx_vcl_balance');
        });

        // §17 shift_assignments (EXTRA_SHIFT / OVERTIME pre-assign by TL)
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by_tl_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->enum('shift_type', ['EXTRA_SHIFT', 'OVERTIME']);
            $table->time('expected_start_time');
            $table->time('expected_end_time');
            $table->decimal('expected_hours', 4, 2);
            $table->decimal('multiplier', 4, 2)->default(1.00);
            $table->text('notes')->nullable();
            $table->enum('status', ['SCHEDULED', 'COMPLETED', 'CANCELLED'])->default('SCHEDULED');
            $table->timestamps();

            $table->index(['user_id', 'date'], 'idx_sa_user_date');
            $table->index('assigned_by_tl_id', 'idx_sa_tl');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
        Schema::dropIfExists('vacation_credit_logs');
        Schema::dropIfExists('vacation_balances');
        Schema::dropIfExists('vacations');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('attendance');
    }
};
