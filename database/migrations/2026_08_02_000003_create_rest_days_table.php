<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // docs/DATABASE_SCHEMA.md §20 — rest_days (monthly quota, marked per date)
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

    public function down(): void
    {
        Schema::dropIfExists('rest_days');
    }
};
