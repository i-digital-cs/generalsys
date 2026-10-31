<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GH-5 — Invoice domain (independent of Gross).
 * One primary invoice per order (UNIQUE order_id).
 * Min-order surcharge tax_class may be NULL until owner approves C-GH-TAX-MIN (blocks ISSUE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gh_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('gh_orders')->restrictOnDelete();
            $table->string('number', 32)->nullable();
            $table->string('status', 20)->default('DRAFT'); // DRAFT|ISSUED
            $table->string('payment_status', 20)->default('UNPAID'); // UNPAID|PARTIAL|PAID (GH-6)
            $table->char('currency', 3)->default('AED');

            $table->unsignedBigInteger('customer_id');
            $table->string('customer_type', 10);
            $table->string('customer_name_ar', 191);
            $table->string('customer_name_en', 191)->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->string('billing_name', 191)->nullable();
            $table->text('billing_address')->nullable();
            $table->string('tax_registration_number', 64)->nullable();

            $table->string('order_reference', 32);
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->string('zone_name_ar', 191)->nullable();
            $table->string('zone_name_en', 191)->nullable();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->string('contract_reference', 64)->nullable();

            $table->decimal('lines_gross', 12, 2)->default(0);
            $table->decimal('item_discount_total', 12, 2)->default(0);
            $table->string('invoice_discount_type', 20)->default('NONE'); // NONE|FIXED|PERCENT
            $table->decimal('invoice_discount_value', 12, 2)->default(0);
            $table->decimal('invoice_discount_amount', 12, 2)->default(0);
            $table->decimal('lines_after_discounts', 12, 2)->default(0);

            $table->decimal('min_order_original_amount', 12, 2)->nullable();
            $table->decimal('min_order_effective_amount', 12, 2)->nullable();
            $table->string('min_order_policy', 32)->nullable();
            $table->string('min_order_override_type', 32)->default('APPLY_DEFAULT');
            $table->decimal('min_order_adjustment_amount', 12, 2)->default(0);
            $table->string('min_order_tax_class', 32)->nullable(); // NULL = unresolved → block ISSUE if surcharge > 0

            $table->decimal('taxable_base', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total_payable', 12, 2)->default(0);
            $table->decimal('standard_vat_rate', 5, 2)->default(5.00);

            $table->unsignedInteger('draft_version')->default(1);
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('order_id', 'uq_gh_invoice_order');
            $table->unique('number', 'uq_gh_invoice_number');
            $table->index(['status', 'issued_at'], 'idx_gh_invoice_status_issued');
            $table->foreign('customer_id')->references('id')->on('gh_customers')->restrictOnDelete();
            $table->foreign('zone_id')->references('id')->on('gh_zones')->nullOnDelete();
            $table->foreign('contract_id')->references('id')->on('gh_b2b_contracts')->nullOnDelete();
        });

        Schema::create('gh_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('gh_invoices')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('received_line_id')->nullable();
            $table->unsignedBigInteger('offering_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('treatment_id')->nullable();

            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity_received')->nullable();
            $table->boolean('qty_differs_from_received')->default(false);

            $table->decimal('unit_price', 12, 2);
            $table->char('currency', 3)->default('AED');
            $table->string('price_source', 20);
            $table->unsignedBigInteger('contract_id')->nullable();

            $table->string('tax_class', 32);
            $table->decimal('tax_rate', 5, 2)->default(0);

            $table->string('item_discount_type', 20)->default('NONE');
            $table->decimal('item_discount_value', 12, 2)->default(0);
            $table->decimal('item_discount_amount', 12, 2)->default(0);

            $table->decimal('line_gross', 12, 2);
            $table->decimal('line_after_item_discount', 12, 2);
            $table->decimal('allocated_invoice_discount', 12, 2)->default(0);
            $table->decimal('taxable_base', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);

            $table->string('product_name_ar', 191);
            $table->string('product_name_en', 191)->nullable();
            $table->string('treatment_name_ar', 191);
            $table->string('treatment_name_en', 191)->nullable();
            $table->string('category_name_ar', 191)->nullable();
            $table->string('category_name_en', 191)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'sort_order'], 'idx_gh_inv_line_sort');
            $table->foreign('received_line_id')->references('id')->on('gh_order_received_lines')->nullOnDelete();
            $table->foreign('offering_id')->references('id')->on('gh_product_offerings')->restrictOnDelete();
        });

        Schema::create('gh_invoice_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('gh_invoices')->cascadeOnDelete();
            $table->string('adjustment_type', 40); // INVOICE_DISCOUNT|MIN_ORDER_SURCHARGE
            $table->decimal('amount', 12, 2);
            $table->string('tax_class', 32)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->string('label_ar', 191)->nullable();
            $table->string('label_en', 191)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'adjustment_type'], 'idx_gh_inv_adj_type');
        });

        Schema::create('gh_min_order_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('gh_invoices')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('gh_orders')->restrictOnDelete();
            $table->string('override_type', 32); // APPLY_DEFAULT|OVERRIDE_AMOUNT|WAIVE
            $table->decimal('original_minimum', 12, 2);
            $table->decimal('effective_minimum', 12, 2);
            $table->decimal('adjustment_amount', 12, 2)->default(0);
            $table->string('reason', 500);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['order_id', 'recorded_at'], 'idx_gh_min_override_order');
        });

        if (! DB::table('gh_sequences')->where('name', 'invoices')->exists()) {
            DB::table('gh_sequences')->insert([
                'name' => 'invoices',
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gh_min_order_overrides');
        Schema::dropIfExists('gh_invoice_adjustments');
        Schema::dropIfExists('gh_invoice_lines');
        Schema::dropIfExists('gh_invoices');
        DB::table('gh_sequences')->where('name', 'invoices')->delete();
    }
};
