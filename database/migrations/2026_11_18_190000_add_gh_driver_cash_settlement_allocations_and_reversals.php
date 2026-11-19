<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manager-chosen allocation of driver cash settlements to specific collections, and
 * compensating reversals. Additive only: existing collections, settlements, balances and
 * events are not read or changed (pre-existing settlements simply have no allocations).
 *
 * MySQL DDL is not transactional: every step is guarded so a re-run resumes after a partial failure.
 * The (id, driver_id) unique keys exist only so the composite FKs can enforce, in the database,
 * that a settlement and the collections it covers belong to the same driver.
 */
return new class extends Migration
{
    private const ALLOCATIONS = 'gh_driver_cash_settlement_allocations';

    private const REVERSALS = 'gh_driver_cash_settlement_reversals';

    private const REVERSAL_SEQUENCE = 'driver_cash_settlement_reversals';

    public function up(): void
    {
        $this->addUnique('gh_driver_cash_collections', 'uq_gh_dcc_id_driver');
        $this->addUnique('gh_driver_cash_settlements', 'uq_gh_dcs_id_driver');

        if (! Schema::hasTable(self::ALLOCATIONS)) {
            Schema::create(self::ALLOCATIONS, function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('settlement_id');
                $table->unsignedBigInteger('collection_id');
                $table->unsignedBigInteger('driver_id');
                $table->decimal('amount', 12, 2);
                $table->char('currency', 3)->default('AED');
                $table->string('batch_key', 64);
                $table->foreignId('allocated_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('allocated_at');
                $table->timestamps();

                $table->index('collection_id', 'idx_gh_dcsa_collection');
                $table->index('batch_key', 'idx_gh_dcsa_batch');
                $table->index(['driver_id', 'allocated_at'], 'idx_gh_dcsa_driver_allocated');
                $table->foreign(['settlement_id', 'driver_id'], 'fk_gh_dcsa_settlement_driver')
                    ->references(['id', 'driver_id'])->on('gh_driver_cash_settlements')->restrictOnDelete();
                $table->foreign(['collection_id', 'driver_id'], 'fk_gh_dcsa_collection_driver')
                    ->references(['id', 'driver_id'])->on('gh_driver_cash_collections')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable(self::REVERSALS)) {
            Schema::create(self::REVERSALS, function (Blueprint $table) {
                $table->id();
                $table->string('reference', 32);
                $table->unsignedBigInteger('settlement_id');
                $table->unsignedBigInteger('driver_id');
                $table->decimal('amount', 12, 2);
                $table->char('currency', 3)->default('AED');
                $table->string('reason', 500);
                $table->foreignId('reversed_by')->constrained('users')->restrictOnDelete();
                $table->timestamp('reversed_at');
                $table->string('idempotency_key', 64);
                $table->timestamps();

                $table->unique('reference', 'uq_gh_dcsr_reference');
                $table->unique('settlement_id', 'uq_gh_dcsr_settlement');
                $table->unique('idempotency_key', 'uq_gh_dcsr_idempotency');
                $table->index(['driver_id', 'reversed_at'], 'idx_gh_dcsr_driver_reversed');
                $table->foreign(['settlement_id', 'driver_id'], 'fk_gh_dcsr_settlement_driver')
                    ->references(['id', 'driver_id'])->on('gh_driver_cash_settlements')->restrictOnDelete();
            });
        }

        $this->addPositiveAmountCheck(self::ALLOCATIONS, 'chk_gh_dcsa_amount_positive');
        $this->addPositiveAmountCheck(self::REVERSALS, 'chk_gh_dcsr_amount_positive');

        if (! DB::table('gh_sequences')->where('name', self::REVERSAL_SEQUENCE)->exists()) {
            DB::table('gh_sequences')->insert([
                'name' => self::REVERSAL_SEQUENCE,
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('gh_sequences')->where('name', self::REVERSAL_SEQUENCE)->delete();
        Schema::dropIfExists(self::REVERSALS);
        Schema::dropIfExists(self::ALLOCATIONS);
        foreach (['gh_driver_cash_settlements' => 'uq_gh_dcs_id_driver', 'gh_driver_cash_collections' => 'uq_gh_dcc_id_driver'] as $table => $index) {
            if (Schema::hasIndex($table, $index)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropUnique($index));
            }
        }
    }

    private function addUnique(string $table, string $index): void
    {
        if (! Schema::hasIndex($table, $index)) {
            Schema::table($table, fn (Blueprint $t) => $t->unique(['id', 'driver_id'], $index));
        }
    }

    /** SQLite (tests) has no ALTER TABLE ADD CONSTRAINT; the service validates amounts on every engine. */
    private function addPositiveAmountCheck(string $table, string $name): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        $exists = DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $name)
            ->exists();
        if (! $exists) {
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` CHECK (`amount` > 0)");
        }
    }
};
