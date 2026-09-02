<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gross_treasury_vouchers')
            || ! Schema::hasColumn('gross_treasury_vouchers', 'purpose')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE gross_treasury_vouchers MODIFY purpose ENUM('SUPPLIER','EXPENSE','COURIER_COD','OTHER') NOT NULL DEFAULT 'SUPPLIER'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('gross_treasury_vouchers')
            || ! Schema::hasColumn('gross_treasury_vouchers', 'purpose')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::table('gross_treasury_vouchers')->where('purpose', 'OTHER')->delete();
            DB::statement("ALTER TABLE gross_treasury_vouchers MODIFY purpose ENUM('SUPPLIER','EXPENSE','COURIER_COD') NOT NULL DEFAULT 'SUPPLIER'");
        }
    }
};
