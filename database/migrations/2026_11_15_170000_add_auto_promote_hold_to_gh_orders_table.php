<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manager-controlled indefinite hold against automatic LATER→PICKUP / WORKING→DELIVERY promotion.
 * Cleared only by explicit Resume or a deliberate manual forward transition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gh_orders', function (Blueprint $table) {
            $table->boolean('auto_promote_held')->default(false)->after('cancel_reason');
            $table->timestamp('auto_promote_held_at')->nullable()->after('auto_promote_held');
            $table->foreignId('auto_promote_held_by')->nullable()->after('auto_promote_held_at')
                ->constrained('users')->nullOnDelete();
            $table->string('auto_promote_held_reason', 500)->nullable()->after('auto_promote_held_by');
            $table->index(['status', 'auto_promote_held'], 'idx_gh_order_auto_promote_hold');
        });
    }

    public function down(): void
    {
        Schema::table('gh_orders', function (Blueprint $table) {
            $table->dropIndex('idx_gh_order_auto_promote_hold');
            $table->dropConstrainedForeignId('auto_promote_held_by');
            $table->dropColumn([
                'auto_promote_held',
                'auto_promote_held_at',
                'auto_promote_held_reason',
            ]);
        });
    }
};
