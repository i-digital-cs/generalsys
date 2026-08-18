<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-8.4 — record who accepted / rejected / forced a permission or vacation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->foreignId('decided_by')
                ->nullable()
                ->after('team_leader_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('vacations', function (Blueprint $table) {
            $table->foreignId('decided_by')
                ->nullable()
                ->after('team_leader_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by');
        });

        Schema::table('vacations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by');
        });
    }
};
