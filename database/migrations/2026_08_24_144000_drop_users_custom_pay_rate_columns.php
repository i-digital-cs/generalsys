<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-employee online/OT rate overrides removed — payroll always uses
 * base_salary formulas (half = base÷30÷2, full = base÷30, OT = base÷30÷8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'online_half_day_rate',
                'online_full_day_rate',
                'overtime_hourly_rate',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('online_half_day_rate', 12, 2)->default(0)->after('base_salary');
            $table->decimal('online_full_day_rate', 12, 2)->default(0)->after('online_half_day_rate');
            $table->decimal('overtime_hourly_rate', 12, 2)->default(0)->after('online_full_day_rate');
        });
    }
};
