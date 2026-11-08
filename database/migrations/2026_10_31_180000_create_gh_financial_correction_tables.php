<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GH-10 — Credit notes, cash refunds, financial corrections (forward-only).
 * Does not alter issued invoice / confirmed payment money columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gh_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('gh_invoices')->restrictOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('number', 32)->nullable();
            $table->string('status', 32); // DRAFT|PENDING_APPROVAL|APPROVED|ISSUED|REJECTED|CANCELLED
            $table->char('currency', 3)->default('AED');
            $table->string('reason_code', 40);
            $table->text('reason_text');

            $table->decimal('lines_net', 12, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total_credit', 12, 2)->default(0);
            $table->decimal('standard_vat_rate', 5, 2)->default(5.00);

            $table->string('idempotency_key', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique('number', 'uq_gh_cn_number');
            $table->unique('idempotency_key', 'uq_gh_cn_idempotency');
            $table->index(['invoice_id', 'status'], 'idx_gh_cn_invoice_status');
            $table->index(['status', 'created_at'], 'idx_gh_cn_status_created');
            $table->foreign('order_id')->references('id')->on('gh_orders')->restrictOnDelete();
            $table->foreign('customer_id')->references('id')->on('gh_customers')->restrictOnDelete();
        });

        Schema::create('gh_credit_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('gh_credit_notes')->cascadeOnDelete();
            $table->foreignId('invoice_line_id')->constrained('gh_invoice_lines')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('quantity_credited');
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

            $table->index(['credit_note_id', 'sort_order'], 'idx_gh_cn_line_sort');
            $table->index(['invoice_line_id'], 'idx_gh_cn_line_invoice_line');
        });

        Schema::create('gh_credit_note_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('gh_credit_notes')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('gh_invoices')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['credit_note_id', 'id'], 'idx_gh_cn_event_cn');
            $table->index(['invoice_id', 'occurred_at'], 'idx_gh_cn_event_invoice');
        });

        Schema::create('gh_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('gh_invoices')->restrictOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->foreignId('credit_note_id')->nullable()->constrained('gh_credit_notes')->nullOnDelete();
            $table->string('reference', 32)->nullable();
            $table->string('status', 32); // REQUESTED|PENDING_APPROVAL|APPROVED|PAID_OUT|REJECTED|CANCELLED
            $table->string('method', 20)->default('CASH');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('AED');
            $table->string('reason_code', 40);
            $table->text('reason_text');
            $table->text('evidence_notes')->nullable();
            $table->string('idempotency_key', 64);

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('paid_out_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique('reference', 'uq_gh_refund_reference');
            $table->unique('idempotency_key', 'uq_gh_refund_idempotency');
            $table->index(['invoice_id', 'status'], 'idx_gh_refund_invoice_status');
            $table->index(['status', 'created_at'], 'idx_gh_refund_status_created');
            $table->foreign('customer_id')->references('id')->on('gh_customers')->restrictOnDelete();
        });

        Schema::create('gh_refund_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained('gh_refunds')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('gh_invoices')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['refund_id', 'id'], 'idx_gh_refund_event_refund');
            $table->index(['invoice_id', 'occurred_at'], 'idx_gh_refund_event_invoice');
        });

        Schema::create('gh_financial_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->nullable();
            $table->string('correction_type', 40); // PAYMENT_RECORDING_ERROR
            $table->string('status', 32); // REQUESTED|PENDING_APPROVAL|APPROVED|EXECUTED|REJECTED|CANCELLED
            $table->foreignId('invoice_id')->constrained('gh_invoices')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('gh_payments')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('AED');
            $table->text('reason_text');
            $table->string('idempotency_key', 64);
            $table->json('payload')->nullable();

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique('reference', 'uq_gh_fin_corr_reference');
            $table->unique('idempotency_key', 'uq_gh_fin_corr_idempotency');
            $table->index(['invoice_id', 'status'], 'idx_gh_fin_corr_invoice_status');
            $table->index(['payment_id', 'status'], 'idx_gh_fin_corr_payment_status');
        });

        Schema::create('gh_financial_correction_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_correction_id')->constrained('gh_financial_corrections')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('gh_invoices')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['financial_correction_id', 'id'], 'idx_gh_fin_corr_event');
        });

        foreach (['credit_notes' => 1, 'refunds' => 1, 'financial_corrections' => 1] as $name => $next) {
            if (! DB::table('gh_sequences')->where('name', $name)->exists()) {
                DB::table('gh_sequences')->insert([
                    'name' => $name,
                    'next_value' => $next,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gh_financial_correction_events');
        Schema::dropIfExists('gh_financial_corrections');
        Schema::dropIfExists('gh_refund_events');
        Schema::dropIfExists('gh_refunds');
        Schema::dropIfExists('gh_credit_note_events');
        Schema::dropIfExists('gh_credit_note_lines');
        Schema::dropIfExists('gh_credit_notes');
        DB::table('gh_sequences')->whereIn('name', ['credit_notes', 'refunds', 'financial_corrections'])->delete();
    }
};
