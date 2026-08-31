<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gross_expense_types')) {
            Schema::create('gross_expense_types', function (Blueprint $table) {
                $table->id();
                $table->string('name_ar', 120);
                $table->string('name_en', 120);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique('name_en', 'uq_gross_expense_type_en');
            });
        }

        if (DB::table('gross_expense_types')->count() === 0) {
            $now = now();
            DB::table('gross_expense_types')->insert([
                [
                    'name_ar' => 'كهرباء',
                    'name_en' => 'Electricity',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name_ar' => 'إيجار',
                    'name_en' => 'Rent',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name_ar' => 'أخرى',
                    'name_en' => 'Other',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        $otherId = (int) DB::table('gross_expense_types')->where('name_en', 'Other')->value('id');

        if (Schema::hasTable('gross_treasury_vouchers')
            && ! Schema::hasColumn('gross_treasury_vouchers', 'purpose')) {
            Schema::table('gross_treasury_vouchers', function (Blueprint $table) {
                $table->enum('purpose', ['SUPPLIER', 'EXPENSE'])->default('SUPPLIER')->after('type');
                $table->foreignId('expense_type_id')->nullable()->after('supplier_id')
                    ->constrained('gross_expense_types')->nullOnDelete();
                $table->index('purpose', 'idx_gross_treas_voucher_purpose');
            });

            if ($otherId > 0) {
                DB::table('gross_treasury_vouchers')
                    ->where('type', 'RECEIPT')
                    ->whereNull('supplier_id')
                    ->update([
                        'purpose' => 'EXPENSE',
                        'expense_type_id' => $otherId,
                    ]);

                DB::table('gross_treasury_vouchers')
                    ->whereNotNull('supplier_id')
                    ->update(['purpose' => 'SUPPLIER']);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gross_treasury_vouchers', 'expense_type_id')) {
            Schema::table('gross_treasury_vouchers', function (Blueprint $table) {
                $table->dropConstrainedForeignId('expense_type_id');
                $table->dropIndex('idx_gross_treas_voucher_purpose');
                $table->dropColumn('purpose');
            });
        }

        Schema::dropIfExists('gross_expense_types');
    }
};
