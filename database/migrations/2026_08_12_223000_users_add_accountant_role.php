<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-7.1 — Add ACCOUNTANT role (reports to Manager; payroll+advances money path).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('MANAGER','TEAM_LEADER','EMPLOYEE','ACCOUNTANT') NOT NULL DEFAULT 'EMPLOYEE'");

            return;
        }

        // SQLite (tests): enum is a CHECK-less string column — no structural change needed.
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::table('users')->where('role', 'ACCOUNTANT')->update(['role' => 'EMPLOYEE']);
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('MANAGER','TEAM_LEADER','EMPLOYEE') NOT NULL DEFAULT 'EMPLOYEE'");
        }
    }
};
