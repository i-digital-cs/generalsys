<?php

namespace App\Services\Gross;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Wipes Mohra Gross data only (catalog, stock, sales/purchases, treasury).
 * Never touches HR, users, or Product/Service valuation sheets.
 */
class GrossDataResetService
{
    /**
     * Child / dependent tables first. Valuation tables are intentionally absent.
     *
     * @var list<string>
     */
    private const TABLES = [
        'product_expense_month_budgets',
        'gross_treasury_voucher_attachments',
        'gross_treasury_vouchers',
        'gross_shipment_allocations',
        'gross_sales_invoice_lines',
        'gross_sales_invoices',
        'gross_purchase_return_lines',
        'gross_purchase_returns',
        'gross_purchase_invoice_lines',
        'gross_purchase_invoices',
        'gross_stock_movements',
        'gross_stock_balances',
        'gross_item_images',
        'gross_items',
        'gross_item_attributes',
        'gross_categories',
        'gross_customers',
        'gross_suppliers',
        'gross_shipping_companies',
        'gross_invoice_sequences',
        'gross_purchase_invoice_sequences',
        'gross_purchase_return_sequences',
        'gross_treasury_voucher_sequences',
        'gross_expense_types',
        'gross_treasury_accounts',
    ];

    public function fresh(): void
    {
        $this->clearItemImageFiles();

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if ($driver === 'mysql') {
                DB::table($table)->truncate();
            } else {
                DB::table($table)->delete();
            }
        }

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->seedDefaults();
    }

    private function clearItemImageFiles(): void
    {
        if (! Schema::hasTable('gross_item_images')) {
            return;
        }

        $disk = Storage::disk('local');
        foreach (DB::table('gross_item_images')->pluck('file_path') as $path) {
            if (is_string($path) && $path !== '' && $disk->exists($path)) {
                $disk->delete($path);
            }
        }

        if ($disk->exists('gross-item-images')) {
            $disk->deleteDirectory('gross-item-images');
        }
    }

    private function seedDefaults(): void
    {
        $now = now();

        if (Schema::hasTable('gross_treasury_accounts') && DB::table('gross_treasury_accounts')->count() === 0) {
            DB::table('gross_treasury_accounts')->insert([
                [
                    'kind' => 'CASH',
                    'name' => 'Cash',
                    'currency' => 'EGP',
                    'opening_balance' => 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'kind' => 'BANK',
                    'name' => 'Bank',
                    'currency' => 'EGP',
                    'opening_balance' => 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        if (Schema::hasTable('gross_expense_types') && DB::table('gross_expense_types')->count() === 0) {
            $hasSort = Schema::hasColumn('gross_expense_types', 'sort_order');
            $hasRent = Schema::hasColumn('gross_expense_types', 'supports_rent_period');

            $rows = [
                ['name_ar' => 'بنزين', 'name_en' => 'Petrol', 'sort_order' => 10, 'supports_rent_period' => false],
                ['name_ar' => 'إيجار', 'name_en' => 'Rent', 'sort_order' => 20, 'supports_rent_period' => true],
                ['name_ar' => 'كهرباء', 'name_en' => 'Electricity', 'sort_order' => 30, 'supports_rent_period' => false],
                ['name_ar' => 'أخرى', 'name_en' => 'Other', 'sort_order' => 90, 'supports_rent_period' => false],
            ];

            foreach ($rows as $row) {
                $insert = [
                    'name_ar' => $row['name_ar'],
                    'name_en' => $row['name_en'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if ($hasSort) {
                    $insert['sort_order'] = $row['sort_order'];
                }
                if ($hasRent) {
                    $insert['supports_rent_period'] = $row['supports_rent_period'];
                }
                DB::table('gross_expense_types')->insert($insert);
            }
        }
    }
}
