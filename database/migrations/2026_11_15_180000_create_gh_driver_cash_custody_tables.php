<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Driver cash custody ledger (physical cash bag) — independent of customer AR payments.
 * Collections increase unsettled custody; manager settlements decrease it. Settlements are not revenue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gh_driver_cash_collections', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32);
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('gh_orders')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('gh_payments')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('AED');
            $table->string('source', 40)->default('DRIVER_DELIVERY');
            $table->timestamp('collected_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notes', 2000)->nullable();
            $table->string('idempotency_key', 64);
            $table->timestamps();

            $table->unique('reference', 'uq_gh_dcc_reference');
            $table->unique('idempotency_key', 'uq_gh_dcc_idempotency');
            $table->index(['driver_id', 'collected_at'], 'idx_gh_dcc_driver_collected');
            $table->index(['order_id'], 'idx_gh_dcc_order');
        });

        Schema::create('gh_driver_cash_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32);
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('AED');
            $table->timestamp('settled_at');
            $table->foreignId('settled_by')->constrained('users')->restrictOnDelete();
            $table->string('receipt_reference', 191)->nullable();
            $table->string('notes', 2000)->nullable();
            $table->string('idempotency_key', 64);
            $table->timestamps();

            $table->unique('reference', 'uq_gh_dcs_reference');
            $table->unique('idempotency_key', 'uq_gh_dcs_idempotency');
            $table->index(['driver_id', 'settled_at'], 'idx_gh_dcs_driver_settled');
        });

        Schema::create('gh_driver_cash_balances', function (Blueprint $table) {
            $table->foreignId('driver_id')->primary()->constrained('users')->restrictOnDelete();
            $table->decimal('collected_total', 12, 2)->default(0);
            $table->decimal('settled_total', 12, 2)->default(0);
            $table->decimal('outstanding', 12, 2)->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('gh_driver_cash_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->unsignedBigInteger('collection_id')->nullable();
            $table->unsignedBigInteger('settlement_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'occurred_at'], 'idx_gh_dce_driver_occurred');
            $table->foreign('collection_id')->references('id')->on('gh_driver_cash_collections')->nullOnDelete();
            $table->foreign('settlement_id')->references('id')->on('gh_driver_cash_settlements')->nullOnDelete();
        });

        foreach (['driver_cash_collections', 'driver_cash_settlements'] as $seq) {
            if (! DB::table('gh_sequences')->where('name', $seq)->exists()) {
                DB::table('gh_sequences')->insert([
                    'name' => $seq,
                    'next_value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gh_driver_cash_events');
        Schema::dropIfExists('gh_driver_cash_balances');
        Schema::dropIfExists('gh_driver_cash_settlements');
        Schema::dropIfExists('gh_driver_cash_collections');
        DB::table('gh_sequences')->whereIn('name', [
            'driver_cash_collections',
            'driver_cash_settlements',
        ])->delete();
    }
};
