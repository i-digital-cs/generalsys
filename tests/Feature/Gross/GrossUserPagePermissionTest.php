<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossStockMovement;
use App\Models\Gross\GrossUserPage;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Services\Gross\ItemService;
use App\Services\Gross\SalesInvoiceService;
use App\Services\Gross\StockService;
use App\Support\GrossAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssignsGrossPages;
use Tests\TestCase;

/**
 * T-15.6 — Mohra per-employee page permissions.
 */
class GrossUserPagePermissionTest extends TestCase
{
    use AssignsGrossPages;
    use RefreshDatabase;

    private User $manager;

    private User $teamLeader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->manager()->create();
        $this->teamLeader = User::factory()->teamLeader()->create();
    }

    public function test_manager_may_page_without_rows(): void
    {
        $this->assertTrue(GrossAccess::mayPage($this->manager, 'sales'));
        $this->assertTrue(GrossAccess::mayPage($this->manager, 'treasury'));
        $this->assertSame(0, GrossUserPage::query()->count());
    }

    public function test_sales_without_pages_forbidden_with_pages_can_workflow_not_edit(): void
    {
        $sales = User::factory()->sales()->create();
        ValuationAssignment::query()->create([
            'user_id' => $sales->id,
            'assigned_by' => $this->manager->id,
            'module' => 'product',
        ]);

        $this->assertFalse(GrossAccess::mayPage($sales, 'sales'));
        $this->assertFalse(GrossAccess::maySell($sales));

        $this->assignGrossPages($sales, ['items', 'stock', 'customers', 'sales', 'shipping']);

        $this->assertTrue(GrossAccess::mayPage($sales->fresh(), 'sales'));
        $this->assertTrue(GrossAccess::mayPage($sales->fresh(), 'stock'));
        $this->assertFalse(GrossAccess::mayPage($sales->fresh(), 'treasury'));
        $this->assertFalse(GrossAccess::mayAdminMutateSalesInvoice($sales));

        $item = app(ItemService::class)->create([
            'name' => 'Dress',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        app(StockService::class)->adjustIn($item, 10, GrossStockMovement::UNIT_PIECE, $this->manager);

        $draft = app(SalesInvoiceService::class)->createDraft($this->manager, [
            'customer_name' => 'Ali',
            'customer_phone' => '0100',
            'currency' => 'EGP',
        ], [[
            'item_id' => $item->id,
            'qty' => 1,
            'unit' => 'PIECE',
            'unit_price' => 50,
        ]]);

        $this->actingAs($sales->fresh())
            ->get(route('gross.stock.index'))
            ->assertOk();

        $this->actingAs($sales->fresh())
            ->get(route('gross.treasury.index'))
            ->assertForbidden();

        $this->actingAs($sales->fresh())
            ->post(route('gross.sales.confirm', $draft))
            ->assertRedirect();

        $this->assertSame(GrossSalesInvoice::STATUS_CONFIRMED, $draft->fresh()->status);

        $this->actingAs($sales->fresh())
            ->get(route('gross.sales.edit', $draft->fresh()))
            ->assertForbidden();

        $this->actingAs($sales->fresh())
            ->delete(route('gross.sales.destroy', $draft->fresh()))
            ->assertForbidden();
    }

    public function test_employee_form_saves_gross_pages(): void
    {
        $sales = User::factory()->sales()->create([
            'email' => 'sales.pages@example.com',
            'name_ar' => 'بائع',
            'name_en' => 'Seller',
        ]);
        $sales->profile()->create(['employee_code' => 'EMP-SP1']);
        $sales->contracts()->create([
            'hire_date' => '2024-01-01',
            'contract_start' => '2024-01-01',
            'contract_end' => '2026-01-01',
            'contract_type' => 'سنة',
            'is_active' => true,
        ]);

        $payload = [
            'name_ar' => $sales->name_ar,
            'name_en' => $sales->name_en,
            'email' => $sales->email,
            'password' => '',
            'role' => User::ROLE_SALES,
            'currency' => 'EGP',
            'language_preference' => 'en',
            'status' => User::STATUS_ACTIVE,
            'base_salary' => 1000,
            'default_shifts' => [
                ['shift_type' => 'FULL_TIME', 'start' => '09:00', 'end' => '17:00'],
                ['shift_type' => '', 'start' => '', 'end' => ''],
            ],
            'profile' => [
                'national_id' => '29501010101010',
                'birth_date' => '1995-01-01',
                'address' => 'Cairo',
                'phone' => '01000000000',
                'gender' => 'MALE',
                'religion' => null,
                'marital_status' => 'SINGLE',
            ],
            'contract' => [
                'hire_date' => '2024-01-01',
                'contract_start' => '2024-01-01',
                'contract_end' => '2026-01-01',
                'contract_type' => 'سنة',
            ],
            'qualifications' => [],
            'contacts' => [],
            'can_confirm_gross_loss' => 0,
            'valuation_modules' => ['service', 'product'],
            'gross_page_keys' => ['sales', 'stock', 'items'],
        ];

        $this->actingAs($this->manager)
            ->put(route('admin.employees.update', $sales), $payload)
            ->assertRedirect(route('admin.employees.edit', $sales));

        $keys = GrossUserPage::query()->where('user_id', $sales->id)->pluck('page_key')->sort()->values()->all();
        $this->assertSame(['items', 'sales', 'stock'], $keys);
        $this->assertSame(
            ['product', 'service'],
            ValuationAssignment::query()
                ->where('user_id', $sales->id)
                ->orderBy('module')
                ->pluck('module')
                ->all()
        );
    }
}
