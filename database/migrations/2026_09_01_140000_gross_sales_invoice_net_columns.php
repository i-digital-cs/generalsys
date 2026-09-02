<?php

use App\Models\Gross\GrossSalesInvoice;
use App\Services\Gross\SalesInvoiceNetService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gross_sales_invoices', function (Blueprint $table) {
            $table->decimal('returned_merchandise', 12, 2)->default(0)->after('total');
            $table->decimal('net_subtotal', 12, 2)->default(0)->after('returned_merchandise');
            $table->decimal('net_discount', 12, 2)->default(0)->after('net_subtotal');
            $table->decimal('net_total', 12, 2)->default(0)->after('net_discount');
        });

        Schema::table('gross_sales_invoice_lines', function (Blueprint $table) {
            $table->decimal('returned_amount', 12, 2)->default(0)->after('line_total');
            $table->decimal('lost_amount', 12, 2)->default(0)->after('returned_amount');
            $table->decimal('net_line_total', 12, 2)->default(0)->after('lost_amount');
        });

        $net = app(SalesInvoiceNetService::class);

        GrossSalesInvoice::query()
            ->with('lines.allocation')
            ->orderBy('id')
            ->chunkById(100, function ($invoices) use ($net): void {
                foreach ($invoices as $invoice) {
                    $net->recalculateInvoice($invoice);
                }
            });
    }

    public function down(): void
    {
        Schema::table('gross_sales_invoice_lines', function (Blueprint $table) {
            $table->dropColumn(['returned_amount', 'lost_amount', 'net_line_total']);
        });

        Schema::table('gross_sales_invoices', function (Blueprint $table) {
            $table->dropColumn(['returned_merchandise', 'net_subtotal', 'net_discount', 'net_total']);
        });
    }
};
