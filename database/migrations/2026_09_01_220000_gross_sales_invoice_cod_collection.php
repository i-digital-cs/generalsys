<?php

use App\Models\Gross\GrossSalesInvoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gross_sales_invoices', function (Blueprint $table) {
            $table->enum('cod_collection_status', ['NONE', 'PENDING', 'COLLECTED', 'LOST'])
                ->default('NONE')
                ->after('shipping_fee_collected_by');
            $table->decimal('cod_collected_amount', 12, 2)->nullable()->after('cod_collection_status');
            $table->timestamp('cod_collected_at')->nullable()->after('cod_collected_amount');
            $table->foreignId('cod_collected_by')->nullable()->after('cod_collected_at')
                ->constrained('users')->nullOnDelete();
            $table->index('cod_collection_status', 'idx_gross_inv_cod_status');
        });

        $map = [
            GrossSalesInvoice::SHIPPING_FEE_PENDING => GrossSalesInvoice::COD_PENDING,
            GrossSalesInvoice::SHIPPING_FEE_COLLECTED => GrossSalesInvoice::COD_COLLECTED,
            GrossSalesInvoice::SHIPPING_FEE_SETTLED => GrossSalesInvoice::COD_COLLECTED,
            GrossSalesInvoice::SHIPPING_FEE_LOST => GrossSalesInvoice::COD_LOST,
            GrossSalesInvoice::SHIPPING_FEE_NONE => GrossSalesInvoice::COD_NONE,
        ];

        foreach ($map as $feeStatus => $codStatus) {
            DB::table('gross_sales_invoices')
                ->where('shipping_fee_status', $feeStatus)
                ->update(['cod_collection_status' => $codStatus]);
        }

        DB::table('gross_sales_invoices')
            ->whereIn('shipping_fee_status', [
                GrossSalesInvoice::SHIPPING_FEE_COLLECTED,
                GrossSalesInvoice::SHIPPING_FEE_SETTLED,
            ])
            ->update([
                'cod_collected_at' => DB::raw('shipping_fee_collected_at'),
                'cod_collected_by' => DB::raw('shipping_fee_collected_by'),
                'cod_collected_amount' => DB::raw('COALESCE(net_total, total)'),
            ]);

        GrossSalesInvoice::query()
            ->where('cod_collection_status', GrossSalesInvoice::COD_PENDING)
            ->where(function ($q): void {
                $q->whereNull('shipping_company_id')
                    ->orWhere('total', '<=', 0);
            })
            ->update(['cod_collection_status' => GrossSalesInvoice::COD_NONE]);
    }

    public function down(): void
    {
        Schema::table('gross_sales_invoices', function (Blueprint $table) {
            $table->dropForeign(['cod_collected_by']);
            $table->dropIndex('idx_gross_inv_cod_status');
            $table->dropColumn([
                'cod_collection_status',
                'cod_collected_amount',
                'cod_collected_at',
                'cod_collected_by',
            ]);
        });
    }
};
