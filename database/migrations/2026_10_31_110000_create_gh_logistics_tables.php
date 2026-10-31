<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * General House Phase 4 — Logistics: attempts, received lines, confirmation columns.
 * Does not reinterpret historical pickup timestamps (0 rows on TEST at ship time).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gh_orders', function (Blueprint $table) {
            $table->foreignId('pickup_confirmed_by')->nullable()->after('picked_up_at')->constrained('users')->nullOnDelete();
            $table->text('pickup_confirmation_notes')->nullable()->after('pickup_confirmed_by');
            $table->timestamp('delivered_at')->nullable()->after('invoice_issued_at');
            $table->foreignId('delivered_by')->nullable()->after('delivered_at')->constrained('users')->nullOnDelete();
            $table->text('delivery_notes')->nullable()->after('delivered_by');
            $table->timestamp('cancelled_at')->nullable()->after('delivery_notes');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 500)->nullable()->after('cancelled_by');
        });

        // New inserts default to UAE business zone; do not rewrite existing timezone values.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE gh_orders MODIFY timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Dubai'");
        } else {
            // SQLite (tests): change() recreates the column with the new default.
            Schema::table('gh_orders', function (Blueprint $table) {
                $table->string('timezone', 64)->default('Asia/Dubai')->change();
            });
        }

        Schema::create('gh_order_received_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('gh_orders')->cascadeOnDelete();
            $table->foreignId('order_line_id')->nullable()->constrained('gh_order_lines')->nullOnDelete();
            $table->foreignId('offering_id')->constrained('gh_product_offerings')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->enum('discrepancy_type', ['MATCH', 'SHORT', 'OVER', 'ADDITIONAL', 'MISSING'])->default('MATCH');
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
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['order_id', 'sort_order'], 'idx_gh_recv_order_sort');
            $table->index('offering_id', 'idx_gh_recv_offering');
        });

        Schema::create('gh_order_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('gh_orders')->cascadeOnDelete();
            $table->enum('attempt_type', ['PICKUP', 'DELIVERY']);
            $table->enum('outcome', ['FAILED', 'PARTIAL'])->default('FAILED');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamp('recorded_at');
            $table->text('notes')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'attempt_type', 'occurred_at'], 'idx_gh_attempt_order_type_occurred');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gh_order_attempts');
        Schema::dropIfExists('gh_order_received_lines');

        Schema::table('gh_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pickup_confirmed_by');
            $table->dropColumn('pickup_confirmation_notes');
            $table->dropColumn('delivered_at');
            $table->dropConstrainedForeignId('delivered_by');
            $table->dropColumn('delivery_notes');
            $table->dropColumn('cancelled_at');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn('cancel_reason');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE gh_orders MODIFY timezone VARCHAR(64) NOT NULL DEFAULT 'Africa/Cairo'");
        } else {
            Schema::table('gh_orders', function (Blueprint $table) {
                $table->string('timezone', 64)->default('Africa/Cairo')->change();
            });
        }
    }
};
