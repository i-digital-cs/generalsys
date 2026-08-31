<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gross_treasury_accounts')) {
            Schema::create('gross_treasury_accounts', function (Blueprint $table) {
                $table->id();
                $table->enum('kind', ['CASH', 'BANK']);
                $table->string('name', 191);
                $table->enum('currency', ['EGP', 'AED'])->default('EGP');
                $table->decimal('opening_balance', 12, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['kind', 'currency'], 'uq_gross_treas_kind_cur');
            });
        }

        if (! Schema::hasTable('gross_treasury_voucher_sequences')) {
            Schema::create('gross_treasury_voucher_sequences', function (Blueprint $table) {
                $table->id();
                $table->char('year_month', 6);
                $table->enum('kind', ['PAYMENT', 'RECEIPT']);
                $table->unsignedInteger('last_number')->default(0);
                $table->unique(['year_month', 'kind'], 'uq_gross_treas_voucher_seq');
            });
        }

        if (! Schema::hasTable('gross_treasury_vouchers')) {
            Schema::create('gross_treasury_vouchers', function (Blueprint $table) {
                $table->id();
                $table->string('voucher_number', 32);
                $table->enum('type', ['PAYMENT', 'RECEIPT']);
                $table->foreignId('account_id')->constrained('gross_treasury_accounts')->restrictOnDelete();
                $table->foreignId('supplier_id')->nullable()
                    ->constrained('gross_suppliers')->nullOnDelete();
                $table->enum('currency', ['EGP', 'AED']);
                $table->decimal('amount', 12, 2);
                $table->date('voucher_date');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique('voucher_number', 'uq_gross_treas_voucher_number');
                $table->index(['type', 'voucher_date'], 'idx_gross_treas_voucher_type_date');
                $table->index('supplier_id', 'idx_gross_treas_voucher_supplier');
            });
        }

        if (DB::table('gross_treasury_accounts')->count() === 0) {
            $now = now();
            DB::table('gross_treasury_accounts')->insert([
                [
                    'kind' => 'CASH',
                    'name' => 'Cash',
                    'currency' => 'EGP',
                    'opening_balance' => 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'kind' => 'BANK',
                    'name' => 'Bank',
                    'currency' => 'EGP',
                    'opening_balance' => 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gross_treasury_vouchers');
        Schema::dropIfExists('gross_treasury_voucher_sequences');
        Schema::dropIfExists('gross_treasury_accounts');
    }
};
