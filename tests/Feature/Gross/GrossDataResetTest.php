<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossCustomer;
use App\Models\Gross\GrossExpenseType;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossItemAttribute;
use App\Models\Gross\GrossTreasuryAccount;
use App\Models\User;
use App\Models\ValuationSheet;
use App\Services\Gross\GrossDataResetService;
use App\Services\Gross\ItemAttributeService;
use App\Services\Gross\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GrossDataResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_clears_mohra_catalog_stock_and_reseeds_defaults_without_touching_valuation(): void
    {
        $manager = User::factory()->manager()->create();

        app(ItemService::class)->create([
            'name' => 'Shirt',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(ItemAttributeService::class)->create([
            'kind' => GrossItemAttribute::KIND_COLOR,
            'name_ar' => 'أحمر',
            'name_en' => 'Red',
        ]);
        GrossCustomer::query()->create([
            'name' => 'Ali',
            'phone' => '01001234567',
        ]);

        if (DB::getSchemaBuilder()->hasTable('product_expense_month_budgets')) {
            $typeId = GrossExpenseType::query()->value('id')
                ?? GrossExpenseType::query()->create([
                    'name_ar' => 'بنزين',
                    'name_en' => 'Petrol',
                    'is_active' => true,
                ])->id;
            DB::table('product_expense_month_budgets')->insert([
                'expense_type_id' => $typeId,
                'year' => 2026,
                'month' => 9,
                'expected_amount' => 100,
                'currency' => 'EGP',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        ValuationSheet::query()->create([
            'user_id' => $manager->id,
            'module' => 'product',
            'date' => '2026-09-01',
            'status' => ValuationSheet::STATUS_DRAFT,
            'input' => [],
            'result' => [],
        ]);

        $this->assertGreaterThan(0, GrossItem::query()->count());
        $this->assertGreaterThan(0, GrossItemAttribute::query()->count());
        $this->assertGreaterThan(0, GrossCustomer::query()->count());
        $this->assertSame(1, ValuationSheet::query()->count());

        app(GrossDataResetService::class)->fresh();

        $this->assertSame(0, GrossItem::query()->count());
        $this->assertSame(0, GrossItemAttribute::query()->count());
        $this->assertSame(0, GrossCustomer::query()->count());
        if (DB::getSchemaBuilder()->hasTable('product_expense_month_budgets')) {
            $this->assertSame(0, (int) DB::table('product_expense_month_budgets')->count());
        }
        $this->assertSame(1, ValuationSheet::query()->count());
        $this->assertSame(2, GrossTreasuryAccount::query()->count());
        $this->assertGreaterThanOrEqual(4, GrossExpenseType::query()->count());
        $this->assertTrue(GrossExpenseType::query()->where('name_en', 'Petrol')->exists());
    }

    public function test_gross_fresh_command_runs(): void
    {
        $this->artisan('gross:fresh', ['--force' => true])
            ->assertSuccessful();
    }

    public function test_mohra_fresh_alias_runs(): void
    {
        $this->artisan('mohra:fresh', ['--force' => true])
            ->assertSuccessful();
    }
}
