<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * General House — category × treatment (many-to-many).
 * Schema only: no data backfill. Mappings require owner approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gh_category_treatment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('gh_service_categories')->restrictOnDelete();
            $table->foreignId('treatment_id')->constrained('gh_treatments')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'treatment_id'], 'uq_gh_category_treatment');
            $table->index(['treatment_id', 'category_id'], 'idx_gh_cat_treat_treatment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gh_category_treatment');
    }
};
