<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 payroll foundation — docs/DATABASE_SCHEMA.md §13–§16 (T-4.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        // §13 penalty_rules
        Schema::create('penalty_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->enum('type', [
                'LATE_ARRIVAL',
                'LEAVE_PERMISSION',
                'LATE_PERMISSION',
                'EXCUSED_ABSENCE',
                'UNEXCUSED_ABSENCE',
            ]);
            $table->enum('currency', ['EGP', 'AED']);
            $table->decimal('amount_per_unit', 12, 2);
            $table->enum('unit', ['MINUTE', 'HOUR', 'DAY']);
            $table->decimal('double_penalty_multiplier', 4, 2)->default(2.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['type', 'currency'], 'uq_rule_type_currency');
        });

        // §14 payroll_records
        Schema::create('payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->enum('currency', ['EGP', 'AED']);
            $table->decimal('base_salary', 12, 2);

            $table->unsignedSmallInteger('full_time_days')->default(0);
            $table->unsignedSmallInteger('online_half_days')->default(0);
            $table->unsignedSmallInteger('online_full_days')->default(0);
            $table->unsignedSmallInteger('rest_days')->default(0);
            $table->decimal('extra_shift_hours', 6, 2)->default(0);
            $table->decimal('overtime_hours', 6, 2)->default(0);
            $table->unsignedSmallInteger('friday_work_days')->default(0);
            $table->unsignedSmallInteger('day_off_work_days')->default(0);
            $table->unsignedSmallInteger('official_holiday_work_days')->default(0);
            $table->decimal('hours_deduction', 6, 2)->default(0);
            $table->unsignedInteger('late_minutes_total')->default(0);

            $table->decimal('full_time_pay', 12, 2)->default(0);
            $table->decimal('online_half_pay', 12, 2)->default(0);
            $table->decimal('online_full_pay', 12, 2)->default(0);
            $table->decimal('overtime_pay', 12, 2)->default(0);
            $table->decimal('friday_bonus', 12, 2)->default(0);
            $table->decimal('day_off_bonus', 12, 2)->default(0);
            $table->decimal('official_holiday_bonus', 12, 2)->default(0);

            $table->decimal('late_penalty', 12, 2)->default(0);
            $table->decimal('leave_permission_penalty', 12, 2)->default(0);
            $table->decimal('late_permission_penalty', 12, 2)->default(0);
            $table->decimal('excused_absence_penalty', 12, 2)->default(0);
            $table->decimal('unexcused_absence_penalty', 12, 2)->default(0);
            $table->decimal('forced_penalty_surcharge', 12, 2)->default(0);
            $table->decimal('advance_deductions', 12, 2)->default(0);

            $table->decimal('gross_pay', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->enum('status', ['DRAFT', 'APPROVED', 'PAID'])->default('DRAFT');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'month', 'year'], 'uq_pay_user_month');
            $table->index('status', 'idx_pay_status');
        });

        // §15 payroll_adjustments
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_record_id')->constrained('payroll_records')->cascadeOnDelete();
            $table->enum('type', [
                'DEPARTMENT_COMMISSION',
                'SALES_COMMISSION',
                'PROFIT_COMMISSION',
                'BONUS',
                'ADVANCE',
                'OTHER_DEDUCTION',
            ]);
            $table->decimal('amount', 12, 2);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('payroll_record_id', 'idx_adj_payroll');
        });

        // §16 advances
        Schema::create('advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('date_given');
            $table->decimal('deduction_amount_monthly', 12, 2);
            $table->decimal('remaining_amount', 12, 2);
            $table->enum('status', ['ACTIVE', 'FULLY_DEDUCTED'])->default('ACTIVE');
            $table->timestamps();

            $table->index(['user_id', 'status'], 'idx_adv_user_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('advances');
        Schema::dropIfExists('payroll_records');
        Schema::dropIfExists('penalty_rules');
    }
};
