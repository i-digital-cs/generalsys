<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-11.5 — Courier shipping-fee collection: PENDING → COLLECTED; delivered_at audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE gross_sales_invoices MODIFY COLUMN shipping_fee_status ENUM('NONE','PENDING','LOST','SETTLED','COLLECTED') NOT NULL DEFAULT 'NONE'");
        }

        Schema::table('gross_sales_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('gross_sales_invoices', 'shipping_fee_collected_at')) {
                $table->timestamp('shipping_fee_collected_at')->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('gross_sales_invoices', 'shipping_fee_collected_by')) {
                $table->foreignId('shipping_fee_collected_by')->nullable()
                    ->after('shipping_fee_collected_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('gross_sales_invoices', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('shipping_fee_collected_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gross_sales_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('gross_sales_invoices', 'shipping_fee_collected_by')) {
                $table->dropConstrainedForeignId('shipping_fee_collected_by');
            }
            foreach (['shipping_fee_collected_at', 'delivered_at'] as $col) {
                if (Schema::hasColumn('gross_sales_invoices', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::table('gross_sales_invoices')
                ->where('shipping_fee_status', 'COLLECTED')
                ->update(['shipping_fee_status' => 'SETTLED']);
            DB::statement("ALTER TABLE gross_sales_invoices MODIFY COLUMN shipping_fee_status ENUM('NONE','PENDING','LOST','SETTLED') NOT NULL DEFAULT 'NONE'");
        }
    }
};
