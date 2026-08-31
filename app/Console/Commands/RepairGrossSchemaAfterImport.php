<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * After importing an older MySQL dump that already has Mohra Gross tables
 * but an outdated `migrations` table, plain `migrate` fails with
 * "table already exists". This command is safe to re-run:
 *  - ensures SALES role + can_confirm_gross_loss
 *  - records Gross migrations that are already applied as Ran
 *  - then runs any remaining migrations normally
 */
class RepairGrossSchemaAfterImport extends Command
{
    protected $signature = 'hrpro:repair-gross-after-import
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Align Gross schema + migrations table after importing an old DB dump';

    /** @var list<string> */
    private const GROSS_MIGRATIONS = [
        '2026_08_27_150000_create_gross_tables_and_sales_role',
        '2026_08_27_170000_gross_item_attributes',
        '2026_08_27_180000_create_gross_item_images_table',
        '2026_08_27_182900_add_discount_percent_to_gross_sales_invoice_lines',
        '2026_08_27_190000_create_gross_suppliers_table',
        '2026_08_30_150000_create_gross_purchase_invoices_tables',
        '2026_08_30_163000_create_gross_purchase_returns_tables',
        '2026_08_30_170000_gross_sales_shipping_fee_collected',
        '2026_08_30_173000_create_gross_treasury_tables',
        '2026_08_30_180000_gross_treasury_expense_purpose',
    ];

    public function handle(): int
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            $this->error('This repair is for MySQL production/import only.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $this->ensureSalesRoleAndFlag($dry);
        $this->recordAppliedGrossMigrations($dry);

        if ($dry) {
            $this->warn('Dry run — no migrate executed.');

            return self::SUCCESS;
        }

        $this->call('migrate', ['--force' => true]);

        return self::SUCCESS;
    }

    private function ensureSalesRoleAndFlag(bool $dry): void
    {
        if (! Schema::hasColumn('users', 'can_confirm_gross_loss')) {
            $this->line('Will add users.can_confirm_gross_loss');
            if (! $dry) {
                Schema::table('users', function ($table) {
                    $table->boolean('can_confirm_gross_loss')->default(false)->after('role');
                });
            }
        } else {
            $this->info('users.can_confirm_gross_loss already present');
        }

        $type = (string) (DB::selectOne("SHOW COLUMNS FROM users LIKE 'role'")->Type ?? '');
        if (! str_contains($type, "'SALES'")) {
            $this->line('Will add SALES to users.role enum');
            if (! $dry) {
                DB::statement(
                    "ALTER TABLE users MODIFY COLUMN role ENUM('MANAGER','TEAM_LEADER','EMPLOYEE','ACCOUNTANT','ASSISTANT','DRIVER','SALES') NOT NULL DEFAULT 'EMPLOYEE'"
                );
            }
        } else {
            $this->info('users.role already includes SALES');
        }
    }

    private function recordAppliedGrossMigrations(bool $dry): void
    {
        if (! Schema::hasTable('gross_categories')) {
            $this->warn('gross_categories missing — leave migrations pending so normal migrate can create Gross.');

            return;
        }

        $batch = (int) DB::table('migrations')->max('batch') + 1;
        foreach (self::GROSS_MIGRATIONS as $name) {
            if (DB::table('migrations')->where('migration', $name)->exists()) {
                continue;
            }
            if (! $this->migrationLooksApplied($name)) {
                $this->warn("Skip recording {$name} (schema incomplete — migrate should create it).");

                continue;
            }
            $this->line("Record migration as Ran: {$name}");
            if (! $dry) {
                DB::table('migrations')->insert([
                    'migration' => $name,
                    'batch' => $batch,
                ]);
            }
        }
    }

    private function migrationLooksApplied(string $name): bool
    {
        return match ($name) {
            '2026_08_27_150000_create_gross_tables_and_sales_role' => Schema::hasTable('gross_categories')
                && Schema::hasTable('gross_sales_invoices'),
            '2026_08_27_170000_gross_item_attributes' => Schema::hasTable('gross_item_attributes')
                && Schema::hasColumn('gross_items', 'color_id'),
            '2026_08_27_180000_create_gross_item_images_table' => Schema::hasTable('gross_item_images'),
            '2026_08_27_182900_add_discount_percent_to_gross_sales_invoice_lines' => Schema::hasColumn(
                'gross_sales_invoice_lines',
                'discount_percent'
            ),
            '2026_08_27_190000_create_gross_suppliers_table' => Schema::hasTable('gross_suppliers'),
            '2026_08_30_150000_create_gross_purchase_invoices_tables' => Schema::hasTable('gross_purchase_invoices'),
            '2026_08_30_163000_create_gross_purchase_returns_tables' => Schema::hasTable('gross_purchase_returns'),
            '2026_08_30_170000_gross_sales_shipping_fee_collected' => Schema::hasColumn(
                'gross_sales_invoices',
                'shipping_fee_collected_at'
            ),
            '2026_08_30_173000_create_gross_treasury_tables' => Schema::hasTable('gross_treasury_accounts')
                && Schema::hasTable('gross_treasury_vouchers'),
            '2026_08_30_180000_gross_treasury_expense_purpose' => Schema::hasTable('gross_expense_types')
                && Schema::hasColumn('gross_treasury_vouchers', 'purpose'),
            default => false,
        };
    }
}
