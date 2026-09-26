<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gross_user_pages')) {
            Schema::create('gross_user_pages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('page_key', 32);
                $table->timestamps();
                $table->unique(['user_id', 'page_key'], 'uq_gross_user_page');
                $table->index('page_key', 'idx_gross_user_page_key');
            });
        }

        $salesDefaults = config('gross_pages.defaults_sales', []);
        $accountantDefaults = config('gross_pages.defaults_accountant', []);
        $now = now();

        $productUserIds = DB::table('valuation_assignments')
            ->where('module', 'product')
            ->pluck('user_id');

        if ($productUserIds->isEmpty()) {
            return;
        }

        $users = DB::table('users')
            ->whereIn('id', $productUserIds)
            ->whereIn('role', [User::ROLE_SALES, User::ROLE_ACCOUNTANT])
            ->get(['id', 'role']);

        $rows = [];
        foreach ($users as $user) {
            $keys = $user->role === User::ROLE_ACCOUNTANT ? $accountantDefaults : $salesDefaults;
            foreach ($keys as $key) {
                $rows[] = [
                    'user_id' => $user->id,
                    'page_key' => $key,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('gross_user_pages')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gross_user_pages');
    }
};
