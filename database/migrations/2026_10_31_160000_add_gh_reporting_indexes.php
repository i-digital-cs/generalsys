<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GH-8 reporting indexes (no new tables/columns). Safe additive indexes only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gh_payments', function (Blueprint $table) {
            $table->index(['status', 'confirmed_at'], 'idx_gh_payment_status_confirmed');
            $table->index(['status', 'method', 'confirmed_at'], 'idx_gh_payment_status_method_confirmed');
        });

        Schema::table('gh_invoices', function (Blueprint $table) {
            $table->index(['status', 'payment_status'], 'idx_gh_invoice_status_payment');
            $table->index(['customer_type', 'status', 'issued_at'], 'idx_gh_invoice_type_status_issued');
        });

        Schema::table('gh_orders', function (Blueprint $table) {
            $table->index(['created_at'], 'idx_gh_order_created');
            $table->index(['delivered_at'], 'idx_gh_order_delivered');
            $table->index(['picked_up_at'], 'idx_gh_order_picked_up');
            $table->index(['status', 'pickup_ends_at'], 'idx_gh_order_status_pickup_ends');
        });
    }

    public function down(): void
    {
        Schema::table('gh_payments', function (Blueprint $table) {
            $table->dropIndex('idx_gh_payment_status_confirmed');
            $table->dropIndex('idx_gh_payment_status_method_confirmed');
        });

        Schema::table('gh_invoices', function (Blueprint $table) {
            $table->dropIndex('idx_gh_invoice_status_payment');
            $table->dropIndex('idx_gh_invoice_type_status_issued');
        });

        Schema::table('gh_orders', function (Blueprint $table) {
            $table->dropIndex('idx_gh_order_created');
            $table->dropIndex('idx_gh_order_delivered');
            $table->dropIndex('idx_gh_order_picked_up');
            $table->dropIndex('idx_gh_order_status_pickup_ends');
        });
    }
};
