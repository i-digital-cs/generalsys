<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gross_sales_invoice_lines')) {
            return;
        }

        if (Schema::hasColumn('gross_sales_invoice_lines', 'discount_percent')) {
            return;
        }

        Schema::table('gross_sales_invoice_lines', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->default(0)->after('discount_amount');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('gross_sales_invoice_lines', 'discount_percent')) {
            Schema::table('gross_sales_invoice_lines', function (Blueprint $table) {
                $table->dropColumn('discount_percent');
            });
        }
    }
};
