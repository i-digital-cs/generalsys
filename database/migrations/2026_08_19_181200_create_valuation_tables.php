<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valuation_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('module', 20);
            $table->timestamps();
            $table->unique(['user_id', 'module']);
            $table->index('module');
        });

        Schema::create('valuation_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module', 20);
            $table->date('date');
            $table->string('status', 20)->default('DRAFT');
            $table->json('input');
            $table->json('result');
            $table->timestamps();
            $table->unique(['user_id', 'module', 'date']);
            $table->index(['module', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valuation_sheets');
        Schema::dropIfExists('valuation_assignments');
    }
};
