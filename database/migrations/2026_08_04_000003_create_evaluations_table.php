<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly evaluations — docs/DATABASE_SCHEMA.md §12 (T-3.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_leader_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('performance_score', 5, 2)->default(0);
            $table->decimal('behavior_score', 5, 2)->default(0);
            $table->decimal('productivity_score', 5, 2)->default(0);
            $table->decimal('interest_return_score', 5, 2)->default(0);
            $table->decimal('attendance_score', 5, 2)->default(0);
            $table->decimal('total_score', 5, 2)->default(0);
            $table->text('evaluation_text')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'month', 'year'], 'uq_eval_user_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
