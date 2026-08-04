<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vacation Force (product A): REJECTED → FORCED; keep rest day; day still off.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE vacations MODIFY status ENUM(
            'PENDING','APPROVED','REJECTED','FORCED'
        ) NOT NULL DEFAULT 'PENDING'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE vacations MODIFY status ENUM(
            'PENDING','APPROVED','REJECTED'
        ) NOT NULL DEFAULT 'PENDING'");
    }
};
