<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rotation: TL may assign FT / Online as well as EXTRA / OT (schema §17).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite tests recreate from the updated create migration on migrate:fresh.
            return;
        }

        DB::statement("ALTER TABLE shift_assignments MODIFY shift_type ENUM(
            'FULL_TIME','ONLINE_HALF','ONLINE_FULL','EXTRA_SHIFT','OVERTIME'
        ) NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE shift_assignments MODIFY shift_type ENUM(
            'EXTRA_SHIFT','OVERTIME'
        ) NOT NULL");
    }
};
