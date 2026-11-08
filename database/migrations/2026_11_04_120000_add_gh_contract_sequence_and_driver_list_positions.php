<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('gh_sequences')->where('name', 'contracts')->exists()) {
            // Start after existing contract count so historical refs stay unique.
            $maxSuffix = 0;
            foreach (DB::table('gh_b2b_contracts')->pluck('reference') as $ref) {
                if (preg_match('/(\d+)$/', (string) $ref, $m) === 1) {
                    $maxSuffix = max($maxSuffix, (int) $m[1]);
                }
            }
            DB::table('gh_sequences')->insert([
                'name' => 'contracts',
                'next_value' => $maxSuffix + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('gh_driver_list_positions')) {
            Schema::create('gh_driver_list_positions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('driver_user_id');
                $table->string('list_type', 16); // PICKUP | DELIVERY
                $table->unsignedBigInteger('order_id');
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();

                $table->unique(['driver_user_id', 'list_type', 'order_id'], 'uq_gh_driver_list_order');
                $table->index(['driver_user_id', 'list_type', 'position'], 'idx_gh_driver_list_pos');
                $table->foreign('driver_user_id', 'fk_gh_dlp_driver')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('order_id', 'fk_gh_dlp_order')->references('id')->on('gh_orders')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gh_driver_list_positions');
        DB::table('gh_sequences')->where('name', 'contracts')->delete();
    }
};
