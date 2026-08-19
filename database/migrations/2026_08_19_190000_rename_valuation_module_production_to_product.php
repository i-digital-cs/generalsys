<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('valuation_assignments')->where('module', 'production')->update(['module' => 'product']);
        DB::table('valuation_sheets')->where('module', 'production')->update(['module' => 'product']);
    }

    public function down(): void
    {
        DB::table('valuation_assignments')->where('module', 'product')->update(['module' => 'production']);
        DB::table('valuation_sheets')->where('module', 'product')->update(['module' => 'production']);
    }
};
