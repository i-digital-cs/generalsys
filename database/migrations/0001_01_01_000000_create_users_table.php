<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // docs/DATABASE_SCHEMA.md §1 — users
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('email', 190)->unique();
            $table->string('password');
            $table->enum('role', ['MANAGER', 'TEAM_LEADER', 'EMPLOYEE', 'ACCOUNTANT'])->default('EMPLOYEE')->index();
            $table->enum('currency', ['EGP', 'AED']);
            $table->enum('language_preference', ['ar', 'en'])->default('ar');
            $table->foreignId('team_leader_id')->nullable()->index()
                ->constrained('users')->nullOnDelete();
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

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
