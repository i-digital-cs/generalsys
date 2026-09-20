<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-15.1 — add ABSENCE_PERMISSION (إذن غياب) to permissions.type.
 * SQLite stores enum as string — no ALTER needed for tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE permissions MODIFY COLUMN type ENUM('LEAVE_PERMISSION','LATE_PERMISSION','ABSENCE_PERMISSION') NOT NULL");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('permissions')
            ->where('type', 'ABSENCE_PERMISSION')
            ->update(['type' => 'LEAVE_PERMISSION']);

        DB::statement("ALTER TABLE permissions MODIFY COLUMN type ENUM('LEAVE_PERMISSION','LATE_PERMISSION') NOT NULL");
    }
};
