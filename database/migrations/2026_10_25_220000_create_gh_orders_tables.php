<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * General House Phase 3 — Orders foundation (operational, not invoices).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gh_sequences', function (Blueprint $table) {
            $table->string('name', 60)->primary();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });

        DB::table('gh_sequences')->insert([
            'name' => 'orders',
            'next_value' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('gh_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40);
            $table->foreignId('customer_id')->constrained('gh_customers')->restrictOnDelete();
            $table->foreignId('address_id')->constrained('gh_customer_addresses')->restrictOnDelete();
            $table->foreignId('zone_id')->constrained('gh_zones')->restrictOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('gh_b2b_contracts')->nullOnDelete();
            $table->enum('status', ['LATER', 'PICKUP', 'WORKING', 'DELIVERY', 'COMPLETED', 'CANCELLED'])->default('LATER');
            $table->timestamp('pickup_starts_at');
            $table->timestamp('pickup_ends_at');
            $table->string('timezone', 64)->default('Africa/Cairo');
            $table->text('notes')->nullable();
            $table->text('scheduling_notes')->nullable();
            $table->foreignId('pickup_driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivery_driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('estimated_subtotal', 12, 2)->default(0);
            $table->char('currency', 3)->default('AED');
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('invoice_issued_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('reference', 'uq_gh_order_reference');
            $table->index(['status', 'pickup_starts_at'], 'idx_gh_order_status_pickup');
            $table->index(['customer_id', 'status'], 'idx_gh_order_customer_status');
            $table->index(['pickup_driver_id', 'status'], 'idx_gh_order_pickup_driver');
            $table->index(['delivery_driver_id', 'status'], 'idx_gh_order_delivery_driver');
            $table->index('zone_id', 'idx_gh_order_zone');
        });

        Schema::create('gh_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('gh_orders')->cascadeOnDelete();
            $table->foreignId('offering_id')->constrained('gh_product_offerings')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('gh_service_categories')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('gh_products')->nullOnDelete();
            $table->foreignId('treatment_id')->nullable()->constrained('gh_treatments')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price_estimate', 12, 2);
            $table->char('currency', 3)->default('AED');
            $table->enum('price_source', ['STANDARD', 'CONTRACT'])->default('STANDARD');
            $table->foreignId('contract_id')->nullable()->constrained('gh_b2b_contracts')->nullOnDelete();
            $table->string('product_name_ar', 150);
            $table->string('product_name_en', 150);
            $table->string('treatment_name_ar', 120);
            $table->string('treatment_name_en', 120);
            $table->string('category_name_ar', 120)->nullable();
            $table->string('category_name_en', 120)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['order_id', 'sort_order'], 'idx_gh_order_line_order_sort');
            $table->index('offering_id', 'idx_gh_order_line_offering');
        });

        Schema::create('gh_order_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('gh_orders')->cascadeOnDelete();
            $table->foreignId('driver_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('responsibility', ['PICKUP', 'DELIVERY']);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['order_id', 'responsibility', 'is_current'], 'idx_gh_assign_order_resp_current');
            $table->index(['driver_user_id', 'responsibility', 'is_current'], 'idx_gh_assign_driver_resp');
        });

        Schema::create('gh_order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('gh_orders')->cascadeOnDelete();
            $table->string('event_type', 60);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamp('recorded_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'occurred_at'], 'idx_gh_event_order_occurred');
            $table->index('event_type', 'idx_gh_event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gh_order_events');
        Schema::dropIfExists('gh_order_assignments');
        Schema::dropIfExists('gh_order_lines');
        Schema::dropIfExists('gh_orders');
        Schema::dropIfExists('gh_sequences');
    }
};
