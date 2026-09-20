<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-15.1 amend — absence may span a date range (request_date … end_date).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('request_date');
        });

        // Existing rows: single-day range.
        DB::table('permissions')->whereNull('end_date')->update([
            'end_date' => DB::raw('request_date'),
        ]);
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('end_date');
        });
    }
};
