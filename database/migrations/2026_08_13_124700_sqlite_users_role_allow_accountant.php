<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Existing SQLite DBs still have CHECK (role IN MANAGER/TEAM_LEADER/EMPLOYEE).
 * Rebuild users so ACCOUNTANT is allowed (T-7.1 follow-up).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        $sql = (string) (DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'")->sql ?? '');
        if ($sql === '' || str_contains($sql, "'ACCOUNTANT'")) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        Schema::create('users_role_expand_tmp', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('email', 190)->unique();
            $table->string('password');
            $table->enum('role', ['MANAGER', 'TEAM_LEADER', 'EMPLOYEE', 'ACCOUNTANT'])->default('EMPLOYEE')->index();
            $table->enum('currency', ['EGP', 'AED']);
            $table->enum('language_preference', ['ar', 'en'])->default('ar');
            $table->unsignedBigInteger('team_leader_id')->nullable()->index();
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('online_half_day_rate', 12, 2)->default(0);
            $table->decimal('online_full_day_rate', 12, 2)->default(0);
            $table->decimal('overtime_hourly_rate', 12, 2)->default(0);
            $table->enum('shift_type_default', ['FULL_TIME', 'ONLINE_HALF', 'ONLINE_FULL'])->default('FULL_TIME');
            $table->time('work_start_time')->nullable();
            $table->time('work_end_time')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE')->index();
            $table->rememberToken();
            $table->timestamps();
        });

        DB::statement('INSERT INTO users_role_expand_tmp SELECT * FROM users');
        Schema::drop('users');
        Schema::rename('users_role_expand_tmp', 'users');

        // Recreate self-FK (dropped with table rebuild).
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('team_leader_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Irreversible on SQLite without reintroducing the broken CHECK — no-op.
    }
};
