<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-7.4 — Job title (AR+EN) on every user: employees, TLs, accountants, managers.
 * Existing rows get a role-based default when both titles are empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('job_title_ar', 150)->nullable()->after('name_en');
            $table->string('job_title_en', 150)->nullable()->after('job_title_ar');
        });

        DB::table('users')->orderBy('id')->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                $ar = trim((string) ($user->job_title_ar ?? ''));
                $en = trim((string) ($user->job_title_en ?? ''));
                if ($ar !== '' || $en !== '') {
                    continue;
                }

                [$titleAr, $titleEn] = match ((string) $user->role) {
                    'MANAGER' => ['مدير', 'Manager'],
                    'TEAM_LEADER' => ['قائد فريق', 'Team Leader'],
                    'ACCOUNTANT' => ['محاسب', 'Accountant'],
                    default => ['موظف', 'Employee'],
                };

                DB::table('users')->where('id', $user->id)->update([
                    'job_title_ar' => $titleAr,
                    'job_title_en' => $titleEn,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['job_title_ar', 'job_title_en']);
        });
    }
};
