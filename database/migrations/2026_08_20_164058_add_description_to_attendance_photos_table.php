<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * T-6.7 — Optional caption/description per punch photo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_photos', function (Blueprint $table) {
            $table->string('description', 500)->nullable()->after('original_name');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_photos', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
