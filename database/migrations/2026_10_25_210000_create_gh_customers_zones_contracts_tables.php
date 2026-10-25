<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * General House Phase 2 — Customers, addresses, zones, B2B contracts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gh_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->decimal('min_order_amount', 12, 2)->default(0);
            $table->enum('min_order_policy', ['VALIDATION_ONLY', 'SURCHARGE'])->default('SURCHARGE');
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status', 'sort_order'], 'idx_gh_zone_status_sort');
        });

        Schema::create('gh_customers', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['B2C', 'B2B'])->default('B2C');
            $table->string('name_ar', 150);
            $table->string('name_en', 150)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('billing_name', 190)->nullable();
            $table->string('billing_address', 500)->nullable();
            $table->string('tax_registration_number', 80)->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['type', 'status'], 'idx_gh_customer_type_status');
            $table->index('phone', 'idx_gh_customer_phone');
            $table->index('email', 'idx_gh_customer_email');
        });

        Schema::create('gh_customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('gh_customers')->restrictOnDelete();
            $table->foreignId('zone_id')->constrained('gh_zones')->restrictOnDelete();
            $table->string('label', 80);
            $table->string('building', 120)->nullable();
            $table->string('street', 190)->nullable();
            $table->string('area', 120)->nullable();
            $table->string('directions', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default')->default(false);
            // Allows at most one default per customer (multiple NULLs OK).
            $table->unsignedBigInteger('default_for_customer_id')->nullable();
            $table->timestamps();

            $table->unique('default_for_customer_id', 'uq_gh_address_default_customer');
            $table->index(['customer_id', 'is_default'], 'idx_gh_address_customer_default');
            $table->index('zone_id', 'idx_gh_address_zone');
        });

        Schema::create('gh_b2b_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('gh_customers')->restrictOnDelete();
            $table->string('reference', 60);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->enum('status', ['DRAFT', 'ACTIVE', 'EXPIRED', 'CANCELLED'])->default('DRAFT');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('reference', 'uq_gh_contract_reference');
            $table->index(['customer_id', 'status', 'effective_from', 'effective_to'], 'idx_gh_contract_customer_validity');
        });

        Schema::create('gh_b2b_contract_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('gh_b2b_contracts')->restrictOnDelete();
            $table->foreignId('offering_id')->constrained('gh_product_offerings')->restrictOnDelete();
            $table->decimal('contract_price', 12, 2);
            $table->char('currency', 3)->default('AED');
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps();

            $table->unique(['contract_id', 'offering_id'], 'uq_gh_contract_offering');
            $table->index('offering_id', 'idx_gh_contract_offering_offering');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gh_b2b_contract_offerings');
        Schema::dropIfExists('gh_b2b_contracts');
        Schema::dropIfExists('gh_customer_addresses');
        Schema::dropIfExists('gh_customers');
        Schema::dropIfExists('gh_zones');
    }
};
