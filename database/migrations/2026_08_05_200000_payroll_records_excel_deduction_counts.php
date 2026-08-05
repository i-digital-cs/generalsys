<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Excel absence day counts + hours-deduction money on the draft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_records', 'excused_absence_days')) {
                $table->unsignedSmallInteger('excused_absence_days')->default(0)->after('hours_deduction');
            }
            if (! Schema::hasColumn('payroll_records', 'unexcused_absence_days')) {
                $table->unsignedSmallInteger('unexcused_absence_days')->default(0)->after('excused_absence_days');
            }
            if (! Schema::hasColumn('payroll_records', 'hours_deduction_amount')) {
                $table->decimal('hours_deduction_amount', 12, 2)->default(0)->after('hours_deduction');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            foreach (['hours_deduction_amount', 'unexcused_absence_days', 'excused_absence_days'] as $col) {
                if (Schema::hasColumn('payroll_records', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
