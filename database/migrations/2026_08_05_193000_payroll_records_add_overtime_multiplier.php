<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-draft OT multiplier so payslip can show: hours × hourly × multiplier.
 * 0 = fall back to Admin Settings overtime multiplier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_records', 'overtime_multiplier')) {
                $table->decimal('overtime_multiplier', 5, 2)->default(0)->after('overtime_hours');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_records', 'overtime_multiplier')) {
                $table->dropColumn('overtime_multiplier');
            }
        });
    }
};
