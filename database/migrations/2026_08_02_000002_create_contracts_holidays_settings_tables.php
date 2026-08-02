<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // docs/DATABASE_SCHEMA.md §6 — contracts
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('hire_date');
            $table->date('contract_start');
            $table->date('contract_end')->index();
            $table->string('contract_type', 100);
            $table->foreignId('document_id')->nullable()
                ->constrained('employee_documents')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        // docs/DATABASE_SCHEMA.md §18 — official_holidays
        Schema::create('official_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->timestamps();
        });

        // docs/DATABASE_SCHEMA.md §19 — settings
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('official_holidays');
        Schema::dropIfExists('contracts');
    }
};
