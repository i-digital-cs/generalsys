<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Debit adjustments linked to an issued invoice (Option A).
 * Never mutates issued invoice money columns; balance SSOT adds issued debit totals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gh_debit_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('gh_invoices')->restrictOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('number', 32)->nullable();
            $table->string('status', 32); // DRAFT|ISSUED|CANCELLED
            $table->char('currency', 3)->default('AED');
            $table->string('reason_code', 40);
            $table->text('reason_text');

            $table->decimal('lines_net', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total_debit', 12, 2)->default(0);
            $table->decimal('standard_vat_rate', 5, 2)->default(5.00);

            $table->string('idempotency_key', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique('number', 'uq_gh_da_number');
            $table->unique('idempotency_key', 'uq_gh_da_idempotency');
            $table->index(['invoice_id', 'status'], 'idx_gh_da_invoice_status');
            $table->index(['status', 'created_at'], 'idx_gh_da_status_created');
            $table->foreign('order_id')->references('id')->on('gh_orders')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('gh_customers')->restrictOnDelete();
        });

        Schema::create('gh_debit_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debit_adjustment_id')->constrained('gh_debit_adjustments')->cascadeOnDelete();
            $table->string('line_type', 32); // ADDITIONAL_ITEM|QUANTITY_INCREASE|PRICE_INCREASE
            $table->foreignId('invoice_line_id')->nullable()->constrained('gh_invoice_lines')->nullOnDelete();
            $table->foreignId('offering_id')->nullable()->constrained('gh_product_offerings')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->char('currency', 3)->default('AED');
            $table->string('tax_class', 32);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_net', 12, 2);
            $table->decimal('line_vat', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->string('product_name_ar', 191)->nullable();
            $table->string('product_name_en', 191)->nullable();
            $table->string('treatment_name_ar', 191)->nullable();
            $table->string('treatment_name_en', 191)->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['debit_adjustment_id', 'sort_order'], 'idx_gh_da_line_sort');
        });

        Schema::create('gh_debit_adjustment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debit_adjustment_id')->constrained('gh_debit_adjustments')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('gh_invoices')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['debit_adjustment_id', 'id'], 'idx_gh_da_event_da');
            $table->index(['invoice_id', 'occurred_at'], 'idx_gh_da_event_invoice');
        });

        if (! DB::table('gh_sequences')->where('name', 'debit_adjustments')->exists()) {
            DB::table('gh_sequences')->insert([
                'name' => 'debit_adjustments',
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gh_debit_adjustment_events');
        Schema::dropIfExists('gh_debit_adjustment_lines');
        Schema::dropIfExists('gh_debit_adjustments');
        DB::table('gh_sequences')->where('name', 'debit_adjustments')->delete();
    }
};
