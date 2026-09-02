<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gross_treasury_vouchers')) {
            return;
        }

        if (Schema::hasColumn('gross_treasury_vouchers', 'purpose')
            && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE gross_treasury_vouchers MODIFY purpose ENUM('SUPPLIER','EXPENSE','COURIER_COD') NOT NULL DEFAULT 'SUPPLIER'");
        }

        if (! Schema::hasColumn('gross_treasury_vouchers', 'sales_invoice_id')) {
            Schema::table('gross_treasury_vouchers', function (Blueprint $table) {
                $table->foreignId('sales_invoice_id')->nullable()->after('expense_type_id')
                    ->constrained('gross_sales_invoices')->nullOnDelete();
                $table->foreignId('shipping_company_id')->nullable()->after('sales_invoice_id')
                    ->constrained('gross_shipping_companies')->nullOnDelete();
                $table->unique('sales_invoice_id', 'uq_gross_treas_courier_cod_invoice');
                $table->index('shipping_company_id', 'idx_gross_treas_courier_cod_ship');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('gross_treasury_vouchers')) {
            return;
        }

        if (Schema::hasColumn('gross_treasury_vouchers', 'sales_invoice_id')) {
            Schema::table('gross_treasury_vouchers', function (Blueprint $table) {
                $table->dropUnique('uq_gross_treas_courier_cod_invoice');
                $table->dropIndex('idx_gross_treas_courier_cod_ship');
                $table->dropConstrainedForeignId('shipping_company_id');
                $table->dropConstrainedForeignId('sales_invoice_id');
            });
        }

        if (Schema::hasColumn('gross_treasury_vouchers', 'purpose')
            && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::table('gross_treasury_vouchers')->where('purpose', 'COURIER_COD')->delete();
            DB::statement("ALTER TABLE gross_treasury_vouchers MODIFY purpose ENUM('SUPPLIER','EXPENSE') NOT NULL DEFAULT 'SUPPLIER'");
        }
    }
};
