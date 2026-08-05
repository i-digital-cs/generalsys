<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto clock-out after grace past expected end (marked on the attendance row).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance', 'is_auto_clock_out')) {
                $table->boolean('is_auto_clock_out')->default(false)->after('early_departure_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            if (Schema::hasColumn('attendance', 'is_auto_clock_out')) {
                $table->dropColumn('is_auto_clock_out');
            }
        });
    }
};
