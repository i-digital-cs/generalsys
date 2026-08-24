<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** TL/Admin/Assistant may close an open punch at actual time (forgot clock-out). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->foreignId('force_closed_by')->nullable()->after('is_auto_clock_out')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('force_closed_at')->nullable()->after('force_closed_by');
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropConstrainedForeignId('force_closed_by');
            $table->dropColumn('force_closed_at');
        });
    }
};
