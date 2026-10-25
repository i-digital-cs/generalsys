<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * General House Phase 1 — Dynamic Services Catalog (gh_*).
 * Independent of Mohra Gross / HR / valuation schemas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gh_service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->string('description_ar', 500)->nullable();
            $table->string('description_en', 500)->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'sort_order'], 'idx_gh_cat_status_sort');
        });

        Schema::create('gh_treatments', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'sort_order'], 'idx_gh_treat_status_sort');
        });

        Schema::create('gh_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('gh_service_categories')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('image_path', 255)->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('code', 'uq_gh_product_code');
            $table->index(['category_id', 'status', 'sort_order'], 'idx_gh_prod_cat_status_sort');
        });

        Schema::create('gh_product_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('gh_products')->restrictOnDelete();
            $table->foreignId('treatment_id')->constrained('gh_treatments')->restrictOnDelete();
            $table->decimal('standard_price', 12, 2);
            $table->char('currency', 3)->default('AED');
            $table->enum('tax_class', [
                'STANDARD_RATED',
                'ZERO_RATED',
                'EXEMPT',
                'OUT_OF_SCOPE',
            ])->default('STANDARD_RATED');
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'treatment_id'], 'uq_gh_offering_product_treatment');
            $table->index(['status', 'sort_order'], 'idx_gh_offering_status_sort');
            $table->index('treatment_id', 'idx_gh_offering_treatment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gh_product_offerings');
        Schema::dropIfExists('gh_products');
        Schema::dropIfExists('gh_treatments');
        Schema::dropIfExists('gh_service_categories');
    }
};
