<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gh_order_received_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('gh_order_received_lines', 'pricing_review_required')) {
                $table->boolean('pricing_review_required')->default(false)->after('price_source');
            }
            if (! Schema::hasColumn('gh_order_received_lines', 'staff_price_approved')) {
                $table->boolean('staff_price_approved')->default(false)->after('pricing_review_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gh_order_received_lines', function (Blueprint $table) {
            if (Schema::hasColumn('gh_order_received_lines', 'staff_price_approved')) {
                $table->dropColumn('staff_price_approved');
            }
            if (Schema::hasColumn('gh_order_received_lines', 'pricing_review_required')) {
                $table->dropColumn('pricing_review_required');
            }
        });
    }
};
