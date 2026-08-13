<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-7.3 — Employees may have one or both base defaults: FULL_TIME + ONLINE_HALF.
 * ONLINE_FULL as default becomes both (product lock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('shift_types_default')->nullable()->after('shift_type_default');
        });

        DB::table('users')->orderBy('id')->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                $legacy = (string) ($user->shift_type_default ?? 'FULL_TIME');
                $types = match ($legacy) {
                    'ONLINE_HALF' => ['ONLINE_HALF'],
                    'ONLINE_FULL' => ['FULL_TIME', 'ONLINE_HALF'],
                    default => ['FULL_TIME'],
                };

                DB::table('users')->where('id', $user->id)->update([
                    'shift_types_default' => json_encode(array_values($types)),
                    'shift_type_default' => $types[0],
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('shift_types_default');
        });
    }
};
