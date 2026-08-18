<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-7.5 — ASSISTANT role (Admin operations helper).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('MANAGER','TEAM_LEADER','EMPLOYEE','ACCOUNTANT','ASSISTANT') NOT NULL DEFAULT 'EMPLOYEE'");

            return;
        }

        if ($driver !== 'sqlite') {
            return;
        }

        $sql = (string) (DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'")->sql ?? '');
        if ($sql === '' || str_contains($sql, "'ASSISTANT'")) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        Schema::create('users_assistant_role_tmp', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('job_title_ar', 150)->nullable();
            $table->string('job_title_en', 150)->nullable();
            $table->string('email', 190)->unique();
            $table->string('password');
            $table->enum('role', ['MANAGER', 'TEAM_LEADER', 'EMPLOYEE', 'ACCOUNTANT', 'ASSISTANT'])->default('EMPLOYEE')->index();
            $table->enum('currency', ['EGP', 'AED']);
            $table->enum('language_preference', ['ar', 'en'])->default('ar');
            $table->unsignedBigInteger('team_leader_id')->nullable()->index();
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('online_half_day_rate', 12, 2)->default(0);
            $table->decimal('online_full_day_rate', 12, 2)->default(0);
            $table->decimal('overtime_hourly_rate', 12, 2)->default(0);
            $table->enum('shift_type_default', ['FULL_TIME', 'ONLINE_HALF', 'ONLINE_FULL'])->default('FULL_TIME');
            $table->json('shift_types_default')->nullable();
            $table->time('work_start_time')->nullable();
            $table->time('work_end_time')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE')->index();
            $table->rememberToken();
            $table->timestamps();
        });

        $columns = Schema::getColumnListing('users');
        $quoted = implode(', ', array_map(fn (string $col) => '"'.$col.'"', $columns));
        DB::statement("INSERT INTO users_assistant_role_tmp ({$quoted}) SELECT {$quoted} FROM users");

        Schema::drop('users');
        Schema::rename('users_assistant_role_tmp', 'users');

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('team_leader_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::table('users')->where('role', 'ASSISTANT')->update(['role' => 'EMPLOYEE']);
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('MANAGER','TEAM_LEADER','EMPLOYEE','ACCOUNTANT') NOT NULL DEFAULT 'EMPLOYEE'");
        }
    }
};
