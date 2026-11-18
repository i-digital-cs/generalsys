<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhOrder;
use App\Models\User;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Upgrade safety for the gh_orders soft-delete columns on existing data.
 * In-memory sqlite via RefreshDatabase — never touches TEST/LIVE MySQL.
 */
class OrderSoftDeleteMigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_COLUMNS = ['deleted_at', 'deleted_by', 'delete_reason'];

    public function test_rerun_on_an_upgraded_table_is_a_noop(): void
    {
        $order = GhOrder::factory()->create(['status' => GhOrderStatus::LATER]);
        $before = $this->rows();

        $this->migration()->up();

        $this->assertSchemaComplete();
        $this->assertSame($before, $this->rows());
        $this->assertNull(GhOrder::withTrashed()->findOrFail($order->id)->deleted_at);
    }

    public function test_upgrade_of_existing_orders_adds_null_columns_and_leaves_other_data_untouched(): void
    {
        foreach ([GhOrderStatus::LATER, GhOrderStatus::WORKING, GhOrderStatus::COMPLETED, GhOrderStatus::CANCELLED] as $status) {
            GhOrder::factory()->create(['status' => $status]);
        }
        $migration = $this->migration();
        $migration->down();
        foreach (self::NEW_COLUMNS as $column) {
            $this->assertFalse(Schema::hasColumn('gh_orders', $column), $column);
        }
        $before = $this->rows();

        $migration->up();

        $this->assertSchemaComplete();
        $this->assertSame($before, $this->rows(self::NEW_COLUMNS));
        foreach (self::NEW_COLUMNS as $column) {
            $this->assertSame(0, DB::table('gh_orders')->whereNotNull($column)->count(), $column);
        }
        $this->assertSame(4, GhOrder::query()->count(), 'no existing order becomes hidden');
    }

    public function test_rerun_completes_after_a_failure_following_the_first_column(): void
    {
        GhOrder::factory()->create();
        $migration = $this->migration();
        $migration->down();
        Schema::table('gh_orders', fn (Blueprint $t) => $t->timestamp('deleted_at')->nullable());

        $migration->up();

        $this->assertSchemaComplete();
    }

    public function test_rerun_completes_after_a_failure_before_the_foreign_key(): void
    {
        GhOrder::factory()->create();
        $migration = $this->migration();
        $migration->down();
        Schema::table('gh_orders', function (Blueprint $t) {
            $t->timestamp('deleted_at')->nullable();
            $t->unsignedBigInteger('deleted_by')->nullable();
        });
        $this->assertSame(0, $this->deletedByForeignKeys());

        $migration->up();

        $this->assertSchemaComplete();
    }

    public function test_removing_the_deleting_admin_keeps_the_order_and_nulls_the_actor(): void
    {
        $admin = User::factory()->manager()->create();
        $order = GhOrder::factory()->create(['status' => GhOrderStatus::LATER]);
        DB::table('gh_orders')->where('id', $order->id)->update([
            'deleted_at' => now(),
            'deleted_by' => $admin->id,
            'delete_reason' => 'Duplicate',
        ]);

        DB::table('users')->where('id', $admin->id)->delete();

        $row = DB::table('gh_orders')->where('id', $order->id)->first();
        $this->assertNotNull($row);
        $this->assertNull($row->deleted_by);
        $this->assertSame('Duplicate', $row->delete_reason);
        $this->assertNotNull($row->deleted_at);
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_18_160000_add_soft_delete_to_gh_orders_table.php');
    }

    private function assertSchemaComplete(): void
    {
        foreach (self::NEW_COLUMNS as $column) {
            $this->assertTrue(Schema::hasColumn('gh_orders', $column), $column);
        }
        $this->assertSame(1, $this->deletedByForeignKeys());
        $fk = collect(Schema::getForeignKeys('gh_orders'))->firstWhere('columns', ['deleted_by']);
        $this->assertSame('users', $fk['foreign_table']);
        $this->assertSame('set null', strtolower((string) $fk['on_delete']));
    }

    private function deletedByForeignKeys(): int
    {
        return collect(Schema::getForeignKeys('gh_orders'))->where('columns', ['deleted_by'])->count();
    }

    /**
     * @param  list<string>  $except
     * @return list<array<string, mixed>>
     */
    private function rows(array $except = []): array
    {
        return DB::table('gh_orders')->orderBy('id')->get()
            ->map(fn ($r) => array_diff_key((array) $r, array_flip($except)))->all();
    }
}
