<?php

namespace App\Services\Gross;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes Mohra Gross transactional + master data for a clean test cycle.
 * Does not touch HR tables or users.
 */
class GrossDataResetService
{
    /**
     * Child tables first — safe for MySQL (FK checks off) and SQLite (DELETE).
     *
     * @var list<string>
     */
    private const TABLES = [
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
            DB::table('gross_expense_types')->insert([
                [
                    'name_ar' => 'كهرباء',
                    'name_en' => 'Electricity',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name_ar' => 'إيجار',
                    'name_en' => 'Rent',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name_ar' => 'أخرى',
                    'name_en' => 'Other',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }
}
