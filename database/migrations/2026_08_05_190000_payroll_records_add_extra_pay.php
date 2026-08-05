<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Excel «إضافي» — manual extra days and/or typed amount on the draft.
 * Idempotent for DBs that already have columns from the create migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_records', 'extra_days')) {
                $table->unsignedSmallInteger('extra_days')->default(0)->after('official_holiday_work_days');
            }
            if (! Schema::hasColumn('payroll_records', 'extra_pay')) {
                $table->decimal('extra_pay', 12, 2)->default(0)->after('official_holiday_bonus');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_records', 'extra_pay')) {
                $table->dropColumn('extra_pay');
            }
            if (Schema::hasColumn('payroll_records', 'extra_days')) {
                $table->dropColumn('extra_days');
            }
        });
    }
};
