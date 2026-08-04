<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly rest weekdays on users (ISO 1=Mon … 7=Sun).
 * Replaces date-based rest_days table (product lock 2026-08-03).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('rest_weekdays')->nullable()->after('work_end_time');
        });

        // Default Friday (ISO 5) for existing users.
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::table('users')->update(['rest_weekdays' => json_encode([5])]);
        } else {
            DB::table('users')->update(['rest_weekdays' => DB::raw('JSON_ARRAY(5)')]);
        }

        Schema::dropIfExists('rest_days');
    }

    public function down(): void
    {
        Schema::create('rest_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'date'], 'uq_rd_user_date');
            $table->index(['user_id', 'date'], 'idx_rd_user_date');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rest_weekdays');
        });
    }
};
