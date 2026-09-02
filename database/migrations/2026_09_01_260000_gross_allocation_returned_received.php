<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gross_shipment_allocations')) {
            return;
        }

        if (! Schema::hasColumn('gross_shipment_allocations', 'qty_returned_received_pieces')) {
            Schema::table('gross_shipment_allocations', function (Blueprint $table) {
                $table->unsignedInteger('qty_returned_received_pieces')
                    ->default(0)
                    ->after('qty_returned_pieces');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gross_shipment_allocations', 'qty_returned_received_pieces')) {
            Schema::table('gross_shipment_allocations', function (Blueprint $table) {
                $table->dropColumn('qty_returned_received_pieces');
            });
        }
    }
};
