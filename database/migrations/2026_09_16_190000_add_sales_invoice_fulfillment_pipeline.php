<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gross_sales_invoices', function (Blueprint $table) {
            $table->string('fulfillment_status', 32)->nullable()->after('status');
            $table->string('packing_image_path', 500)->nullable()->after('notes');
            $table->string('packing_image_original_name', 191)->nullable()->after('packing_image_path');
            $table->string('packing_image_mime', 100)->nullable()->after('packing_image_original_name');
            $table->unsignedInteger('packing_image_size_bytes')->nullable()->after('packing_image_mime');
            $table->timestamp('packing_uploaded_at')->nullable()->after('packing_image_size_bytes');
            $table->foreignId('packing_uploaded_by')->nullable()->after('packing_uploaded_at')
                ->constrained('users')->nullOnDelete();
            $table->index('fulfillment_status');
        });

        // Existing live invoices: stock already with courier after old confirm.
        DB::table('gross_sales_invoices')
            ->where('status', 'CONFIRMED')
            ->whereNull('fulfillment_status')
            ->update(['fulfillment_status' => 'WITH_COURIER']);

        DB::table('gross_sales_invoices')
            ->where('status', 'DELIVERED')
            ->whereNull('fulfillment_status')
            ->update(['fulfillment_status' => 'WITH_COURIER']);

        DB::table('gross_sales_invoices')
            ->where('status', 'REFUSED')
            ->whereNull('fulfillment_status')
            ->update(['fulfillment_status' => 'WITH_COURIER']);
    }

    public function down(): void
    {
        Schema::table('gross_sales_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('packing_uploaded_by');
            $table->dropIndex(['fulfillment_status']);
            $table->dropColumn([
                'fulfillment_status',
                'packing_image_path',
                'packing_image_original_name',
                'packing_image_mime',
                'packing_image_size_bytes',
                'packing_uploaded_at',
            ]);
        });
    }
};
