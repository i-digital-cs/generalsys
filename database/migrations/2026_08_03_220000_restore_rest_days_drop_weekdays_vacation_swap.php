<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product lock 2026-08-03 (reverted weekdays):
 * - Restore date-based rest_days
 * - Drop users.rest_weekdays
 * - Vacation swap: replace_rest_date (+ optional FK)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rest_days')) {
            Schema::create('rest_days', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->date('date');
                $table->foreignId('created_by')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['user_id', 'date']);
                $table->index(['user_id', 'date']);
            });
        }

        if (Schema::hasColumn('users', 'rest_weekdays')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('rest_weekdays');
            });
        }

        Schema::table('vacations', function (Blueprint $table) {
            $table->date('replace_rest_date')->nullable()->after('days_requested');
            $table->foreignId('replace_rest_day_id')->nullable()->after('replace_rest_date')
                ->constrained('rest_days')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vacations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replace_rest_day_id');
            $table->dropColumn('replace_rest_date');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('rest_weekdays')->nullable()->after('work_end_time');
        });

        Schema::dropIfExists('rest_days');
    }
};
