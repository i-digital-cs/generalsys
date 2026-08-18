<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-8.1 — max two team leaders per employee via pivot; drop users.team_leader_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_team_leaders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('team_leader_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'team_leader_id']);
            $table->index('team_leader_id');
        });

        if (! Schema::hasColumn('users', 'team_leader_id')) {
            return;
        }

        $now = now();
        $rows = DB::table('users')->whereNotNull('team_leader_id')->get(['id', 'team_leader_id']);
        foreach ($rows as $row) {
            $leaderExists = DB::table('users')->where('id', $row->team_leader_id)->exists();
            if (! $leaderExists) {
                continue;
            }

            DB::table('employee_team_leaders')->insert([
                'user_id' => $row->id,
                'team_leader_id' => $row->team_leader_id,
                'assigned_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->dropUsersTeamLeaderId();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'team_leader_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('team_leader_id')->nullable()->index()
                    ->constrained('users')->nullOnDelete();
            });
        }

        $firsts = DB::table('employee_team_leaders')
            ->selectRaw('user_id, MIN(id) as first_id')
            ->groupBy('user_id')
            ->get();

        foreach ($firsts as $row) {
            $pivot = DB::table('employee_team_leaders')->where('id', $row->first_id)->first();
            if ($pivot === null) {
                continue;
            }
            DB::table('users')->where('id', $pivot->user_id)->update([
                'team_leader_id' => $pivot->team_leader_id,
            ]);
        }

        Schema::dropIfExists('employee_team_leaders');
    }

    private function dropUsersTeamLeaderId(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['team_leader_id']);
                $table->dropColumn('team_leader_id');
            });

            return;
        }

        if ($driver !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('team_leader_id');
            });

            return;
        }

        Schema::disableForeignKeyConstraints();

        Schema::create('users_drop_tl_tmp', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 150);
            $table->string('name_en', 150);
            $table->string('job_title_ar', 150)->nullable();
            $table->string('job_title_en', 150)->nullable();
            $table->string('email', 190)->unique();
            $table->string('password');
            $table->enum('role', ['MANAGER', 'TEAM_LEADER', 'EMPLOYEE', 'ACCOUNTANT', 'ASSISTANT'])->default('EMPLOYEE')->index();
            $table->enum('currency', ['EGP', 'AED']);
            $table->enum('language_preference', ['ar', 'en'])->default('ar');
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('online_half_day_rate', 12, 2)->default(0);
            $table->decimal('online_full_day_rate', 12, 2)->default(0);
            $table->decimal('overtime_hourly_rate', 12, 2)->default(0);
            $table->enum('shift_type_default', ['FULL_TIME', 'ONLINE_HALF', 'ONLINE_FULL'])->default('FULL_TIME');
            $table->json('shift_types_default')->nullable();
            $table->time('work_start_time')->nullable();
            $table->time('work_end_time')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE')->index();
            $table->rememberToken();
            $table->timestamps();
        });

        $newCols = Schema::getColumnListing('users_drop_tl_tmp');
        $oldCols = Schema::getColumnListing('users');
        $columns = array_values(array_intersect($oldCols, $newCols));
        $quoted = implode(', ', array_map(fn (string $col) => '"'.$col.'"', $columns));
        DB::statement("INSERT INTO users_drop_tl_tmp ({$quoted}) SELECT {$quoted} FROM users");

        Schema::drop('users');
        Schema::rename('users_drop_tl_tmp', 'users');

        Schema::enableForeignKeyConstraints();
    }
};
