<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-7.2 — Required punch GPS + soft office geofence flags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->decimal('clock_in_lat', 10, 7)->nullable()->after('clock_in');
            $table->decimal('clock_in_lng', 10, 7)->nullable()->after('clock_in_lat');
            $table->unsignedInteger('clock_in_accuracy_m')->nullable()->after('clock_in_lng');
            $table->unsignedInteger('clock_in_distance_m')->nullable()->after('clock_in_accuracy_m');
            $table->boolean('clock_in_outside_geofence')->default(false)->after('clock_in_distance_m');

            $table->decimal('clock_out_lat', 10, 7)->nullable()->after('clock_out');
            $table->decimal('clock_out_lng', 10, 7)->nullable()->after('clock_out_lat');
            $table->unsignedInteger('clock_out_accuracy_m')->nullable()->after('clock_out_lng');
            $table->unsignedInteger('clock_out_distance_m')->nullable()->after('clock_out_accuracy_m');
            $table->boolean('clock_out_outside_geofence')->default(false)->after('clock_out_distance_m');
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn([
                'clock_in_lat',
                'clock_in_lng',
                'clock_in_accuracy_m',
                'clock_in_distance_m',
                'clock_in_outside_geofence',
                'clock_out_lat',
                'clock_out_lng',
                'clock_out_accuracy_m',
                'clock_out_distance_m',
                'clock_out_outside_geofence',
            ]);
        });
    }
};
