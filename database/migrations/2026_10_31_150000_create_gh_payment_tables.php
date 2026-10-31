<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GH-6 — Payment ledger (independent of Gross).
 * Approved methods: CASH, ONLINE. Bank transfer / gateway capture / refunds disabled at app layer.
 * CONFIRMED rows are immutable (no destructive updates/deletes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gh_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('gh_invoices')->restrictOnDelete();
            $table->string('reference', 32);
            $table->string('method', 20); // CASH|ONLINE (BANK rejected in app)
            $table->string('status', 20); // PENDING|PENDING_REVIEW|CONFIRMED|REJECTED|CANCELLED
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('AED');
            $table->string('external_ref', 191)->nullable();
            $table->string('idempotency_key', 64);
            $table->text('notes')->nullable();

            $table->foreignId('collector_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('occurred_at');
            $table->timestamp('recorded_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique('reference', 'uq_gh_payment_reference');
            $table->unique('idempotency_key', 'uq_gh_payment_idempotency');
            $table->index(['invoice_id', 'status'], 'idx_gh_payment_invoice_status');
            $table->unique(['invoice_id', 'external_ref'], 'uq_gh_payment_invoice_ext_ref');
        });

        Schema::create('gh_payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('gh_payments')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('gh_invoices')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'occurred_at'], 'idx_gh_pay_event_invoice');
            $table->index(['payment_id', 'id'], 'idx_gh_pay_event_payment');
        });

        if (! DB::table('gh_sequences')->where('name', 'payments')->exists()) {
            DB::table('gh_sequences')->insert([
                'name' => 'payments',
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gh_payment_events');
        Schema::dropIfExists('gh_payments');
        DB::table('gh_sequences')->where('name', 'payments')->delete();
    }
};
