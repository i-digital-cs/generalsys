<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin soft deletion of LATER / PICKUP orders. Schema only — no data is read or changed.
 *
 * MySQL DDL is not transactional: each column and the FK are guarded separately so a
 * re-run after a partial failure completes the remaining steps instead of skipping them.
 */
return new class extends Migration
{
    private const FOREIGN_KEY = 'gh_orders_deleted_by_foreign';

    public function up(): void
    {
        if (! Schema::hasColumn('gh_orders', 'deleted_at')) {
            Schema::table('gh_orders', function (Blueprint $table) {
                $table->timestamp('deleted_at')->nullable()->after('cancel_reason');
            });
        }

        if (! Schema::hasColumn('gh_orders', 'deleted_by')) {
            Schema::table('gh_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
            });
        }

        if (! $this->hasDeletedByForeignKey()) {
            Schema::table('gh_orders', function (Blueprint $table) {
                $table->foreign('deleted_by', self::FOREIGN_KEY)->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('gh_orders', 'delete_reason')) {
            Schema::table('gh_orders', function (Blueprint $table) {
                $table->string('delete_reason', 500)->nullable()->after('deleted_by');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasDeletedByForeignKey()) {
            Schema::table('gh_orders', function (Blueprint $table) {
                $table->dropForeign(['deleted_by']);
            });
        }

        $columns = array_values(array_filter(
            ['deleted_at', 'deleted_by', 'delete_reason'],
            fn (string $column) => Schema::hasColumn('gh_orders', $column),
        ));
        if ($columns !== []) {
            Schema::table('gh_orders', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    private function hasDeletedByForeignKey(): bool
    {
        return collect(Schema::getForeignKeys('gh_orders'))
            ->contains(fn (array $fk) => $fk['columns'] === ['deleted_by']);
    }
};
