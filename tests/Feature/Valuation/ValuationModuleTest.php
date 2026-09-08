<?php

namespace Tests\Feature\Valuation;

use App\Models\User;
use App\Models\ValuationAssignment;
use App\Models\ValuationSheet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValuationModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_assigns_user_to_service_and_user_fills_sheet(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-19 12:00:00', config('app.timezone')));
        $manager = User::factory()->manager()->create();
        $hamad = User::factory()->employee()->create(['name_en' => 'Hamad']);

        $this->actingAs($manager)
            ->put(route('valuation.assignments.update', $hamad), [
                'modules' => ['service'],
            ])
            ->assertRedirect(route('valuation.assignments.index'));

        $this->assertDatabaseHas('valuation_assignments', [
            'user_id' => $hamad->id,
            'module' => ValuationAssignment::MODULE_SERVICE,
        ]);

        $this->withSession(['locale' => 'en'])->actingAs($hamad)
            ->post(route('valuation.sheets.store', 'service'), [
                'date' => '2026-08-19',
                'status' => ValuationSheet::STATUS_SUBMITTED,
                'input' => [
                    'market_status' => 'Busy market',
                    'haroon_news' => 'No Haroon notes',
                    'laundry_king_positives' => 'King on time',
                    'drivers_notes' => 'Eighteen trips',
                    'drivers' => [
                        ['user_id' => $hamad->id, 'orders' => 30, 'target' => 20, 'score' => 80, 'notes' => 'Fast delivery'],
                        ['name' => 'Freelance Driver', 'orders' => 40, 'target' => 25, 'score' => 100, 'notes' => 'Guest notes'],
                        ['user_id' => '', 'orders' => ''],
                    ],
                    'provider_mart' => 100,
                    'actual_b2b_count' => 17,
                    'actual_b2c_count' => 0,
                    'target_b2b_count' => 40,
                    'target_b2c_count' => 5,
                    'actual_b2b_amount' => 1274.55,
                    'actual_b2c_amount' => 32,
                    'target_b2b_amount' => 5000,
                    'target_b2c_amount' => 7000,
                    'kpi_base' => 2500,
                    'cashflow_base' => 2460,
                ],
            ])
            ->assertRedirect();

        $sheet = ValuationSheet::query()->firstOrFail();
        $this->assertSame('loss', $sheet->result['kpi_label']);
        $this->assertSame('service', $sheet->module);
        $this->assertCount(2, $sheet->input['drivers']);
        $this->assertEqualsWithDelta(20.0, $sheet->input['drivers'][0]['target'], 0.01);
        $this->assertEqualsWithDelta(10.0, $sheet->result['drivers'][0]['over_target'], 0.01);
        $this->assertSame('Freelance Driver', $sheet->input['drivers'][1]['name']);
        $this->assertEqualsWithDelta(15.0, $sheet->result['drivers'][1]['over_target'], 0.01);
        $this->assertEqualsWithDelta(80.0, $sheet->input['drivers'][0]['score'], 0.01);
        $this->assertSame('Fast delivery', $sheet->input['drivers'][0]['notes']);
        $this->assertSame('Guest notes', $sheet->input['drivers'][1]['notes']);
        $this->assertEqualsWithDelta(90.0, $sheet->result['drivers_score_avg'], 0.01);
        $this->assertEqualsWithDelta(90.0, $sheet->input['drivers_score'], 0.01);

        $this->withSession(['locale' => 'en'])->actingAs($hamad)
            ->get(route('valuation.sheets.show', ['module' => 'service', 'sheet' => $sheet]))
            ->assertOk()
            ->assertSee(__('valuation.fields.general_target'), false)
            ->assertSee(__('valuation.fields.execution'), false)
            ->assertSee('37.78%', false)
            ->assertSee('42.50%', false)
            ->assertSee('0.00%', false)
            ->assertSee('10.89%', false)
            ->assertSee('25.49%', false)
            ->assertSee('0.46%', false)
            ->assertSee('52.26%', false)
            ->assertSee(__('valuation.labels.loss'), false)
            ->assertSee(__('valuation.sections.market'), false)
            ->assertSee('Busy market', false)
            ->assertSee('No Haroon notes', false)
            ->assertSee(__('valuation.sections.laundry_king'), false)
            ->assertSee('King on time', false)
            ->assertSee(__('valuation.sections.drivers'), false)
            ->assertSee(__('valuation.drivers.over_target', ['x' => 10]), false)
            ->assertSee('Freelance Driver', false)
            ->assertSee(__('valuation.drivers.over_target', ['x' => 15]), false)
            ->assertSee('Fast delivery', false)
            ->assertSee('Guest notes', false)
            ->assertSee('80.00%', false)
            ->assertSee('100.00%', false)
            ->assertSee(__('valuation.fields.provider_mart'), false)
            ->assertSee('100%', false)
            ->assertSee(__('valuation.export_pdf'), false);

        $this->actingAs($hamad)
            ->get(route('valuation.sheets.export.pdf', ['module' => 'service', 'sheet' => $sheet]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $pdfHtml = view('exports.valuation-pdf', [
            'sheet' => $sheet->load('user'),
            'module' => 'service',
            'input' => $sheet->input,
            'result' => $sheet->result,
            'driverUsers' => collect([$hamad->id => $hamad]),
        ])->render();
        $this->assertStringContainsString('37.78%', $pdfHtml);
        $this->assertStringContainsString("font-family: 'Tajawal'", $pdfHtml);
        $this->assertStringContainsString('King on time', $pdfHtml);
        $this->assertStringContainsString('class="stars"', $pdfHtml);
        $this->assertStringNotContainsString('*****', $pdfHtml);
        $this->assertStringContainsString('Over target 10', $pdfHtml);
        $this->assertStringNotContainsString('****-', $pdfHtml);

        app()->setLocale('ar');
        $arHtml = view('exports.valuation-pdf', [
            'sheet' => $sheet,
            'module' => 'service',
            'input' => $sheet->input,
            'result' => $sheet->result,
        ])->render();
        $this->assertMatchesRegularExpression('/[\x{FE70}-\x{FEFF}]/u', $arHtml);
        $this->assertStringNotContainsString('ورقة التقييم اليومية', $arHtml);
        $this->assertStringContainsString('text-align: right', $arHtml);
        $this->assertStringContainsString('(0–100)', $arHtml);
        $this->assertStringContainsString('B2B', $arHtml);
        $this->assertStringNotContainsString('B٢B', $arHtml);
        $this->assertStringContainsString('class="stars"', $arHtml);
        $this->assertStringContainsString('&#9733;', $arHtml);
        app()->setLocale('en');

        $this->withSession(['locale' => 'en'])->actingAs($hamad)
            ->get(route('valuation.sheets.index', 'service'))
            ->assertOk()
            ->assertSee(__('valuation.stats.title'), false)
            ->assertSee(__('valuation.stats.gross'), false)
            ->assertSee('1,306.55', false)
            ->assertSee(__('valuation.stats.b2b_sales'), false)
            ->assertSee(__('valuation.list'), false)
            ->assertSee(__('general.actions'), false)
            ->assertSee('data-table-filter-root', false)
            ->assertSee('data-table-search', false)
            ->assertSee('data-filter-status', false);

        $this->withSession(['locale' => 'en'])->actingAs($hamad)
            ->get(route('portal.service'))
            ->assertOk()
            ->assertSee(__('valuation.stats.title'), false)
            ->assertSee('1,306.55', false);

        $this->actingAs($hamad)
            ->get(route('portal.product'))
            ->assertForbidden();

        $this->actingAs($hamad)
            ->get(route('valuation.sheets.index', 'product'))
            ->assertForbidden();
    }

    public function test_other_user_cannot_view_sheet_manager_can(): void
    {
        $manager = User::factory()->manager()->create();
        $hamad = User::factory()->employee()->create();
        $ahmed = User::factory()->employee()->create();
        ValuationAssignment::query()->create([
            'user_id' => $hamad->id,
            'assigned_by' => $manager->id,
            'module' => 'service',
        ]);

        $this->actingAs($hamad)
            ->post(route('valuation.sheets.store', 'service'), [
                'date' => '2026-08-19',
                'status' => ValuationSheet::STATUS_DRAFT,
                'input' => ['drivers_score' => 1],
            ]);

        $sheet = ValuationSheet::query()->firstOrFail();

        $this->actingAs($ahmed)
            ->get(route('valuation.sheets.show', ['module' => 'service', 'sheet' => $sheet]))
            ->assertForbidden();

        $this->actingAs($ahmed)
            ->get(route('valuation.sheets.export.pdf', ['module' => 'service', 'sheet' => $sheet]))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('valuation.sheets.show', ['module' => 'service', 'sheet' => $sheet]))
            ->assertOk();

        $this->actingAs($ahmed)
            ->put(route('valuation.assignments.update', $hamad), ['modules' => ['product']])
            ->assertForbidden();
    }

    public function test_manager_can_delete_sheet_others_cannot(): void
    {
        $manager = User::factory()->manager()->create();
        $hamad = User::factory()->employee()->create();
        $ahmed = User::factory()->employee()->create();
        ValuationAssignment::query()->create([
            'user_id' => $hamad->id,
            'assigned_by' => $manager->id,
            'module' => 'service',
        ]);

        $sheet = ValuationSheet::query()->create([
            'user_id' => $hamad->id,
            'module' => 'service',
            'date' => '2026-08-19',
            'status' => ValuationSheet::STATUS_SUBMITTED,
            'input' => [],
            'result' => ['actual_amount_total' => 100, 'kpi_label' => 'loss'],
        ]);

        $this->actingAs($hamad)
            ->delete(route('valuation.sheets.destroy', ['module' => 'service', 'sheet' => $sheet]))
            ->assertForbidden();

        $this->actingAs($ahmed)
            ->delete(route('valuation.sheets.destroy', ['module' => 'service', 'sheet' => $sheet]))
            ->assertForbidden();

        $this->assertDatabaseHas('valuation_sheets', ['id' => $sheet->id]);

        $this->actingAs($manager)
            ->get(route('valuation.sheets.index', 'service'))
            ->assertOk()
            ->assertSee(__('valuation.delete'), false);

        $this->actingAs($manager)
            ->delete(route('valuation.sheets.destroy', ['module' => 'service', 'sheet' => $sheet]))
            ->assertRedirect(route('valuation.sheets.index', 'service'));

        $this->assertDatabaseMissing('valuation_sheets', ['id' => $sheet->id]);
    }

    public function test_form_keeps_sales_spread_and_king_fields_once(): void
    {
        $manager = User::factory()->manager()->create();
        $hamad = User::factory()->employee()->create();
        ValuationAssignment::query()->create([
            'user_id' => $hamad->id,
            'assigned_by' => $manager->id,
            'module' => 'service',
        ]);

        $html = $this->withSession(['locale' => 'en'])->actingAs($hamad)
            ->get(route('valuation.sheets.form', 'service'))
            ->assertOk()
            ->assertSee(__('valuation.fields.sales_spread'), false)
            ->assertSee(__('valuation.fields.laundry_king_positives'), false)
            ->assertSee(__('valuation.fields.laundry_king_negatives'), false)
            ->assertSee('id="target_count_total"', false)
            ->assertSee('name="input[target_count_total]"', false)
            ->assertSee(__('valuation.fields.score'), false)
            ->assertSee(__('valuation.drivers.add'), false)
            ->assertSee('name="input[drivers][0][user_id]"', false)
            ->assertSee('name="input[drivers][0][name]"', false)
            ->assertSee('name="input[drivers][0][orders]"', false)
            ->assertSee('name="input[drivers][0][target]"', false)
            ->assertSee('name="input[drivers][0][score]"', false)
            ->assertSee('name="input[drivers][0][notes]"', false)
            ->assertSee(__('valuation.drivers.or_manual_hint'), false)
            ->assertDontSee('id="drivers_score"', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'id="sales_spread"'));
        $this->assertSame(1, substr_count($html, 'id="laundry_king_positives"'));
        $this->assertSame(1, substr_count($html, 'id="laundry_king_negatives"'));
    }

    public function test_product_form_uses_mohra_valuation_labels(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        ValuationAssignment::query()->create([
            'user_id' => $employee->id,
            'assigned_by' => $manager->id,
            'module' => 'product',
        ]);

        $this->withSession(['locale' => 'en'])->actingAs($employee)
            ->get(route('valuation.sheets.form', 'product'))
            ->assertOk()
            ->assertSee(__('valuation.mohra.sections.laundry_king'), false)
            ->assertSee(__('valuation.mohra.sections.drivers'), false)
            ->assertSee(__('valuation.mohra.fields.sales_outdoor'), false)
            ->assertSee(__('valuation.mohra.fields.haroon_news'), false)
            ->assertSee('name="input[target_count_total]"', false)
            ->assertSee('value="35"', false)
            ->assertSee(__('valuation.mohra.drivers.add'), false)
            ->assertSee('name="input[drivers][0][user_id]"', false)
            ->assertDontSee(__('valuation.sections.laundry_king'), false)
            ->assertDontSee(__('valuation.sections.providers'), false);

        $this->actingAs($employee)
            ->post(route('valuation.sheets.store', 'product'), [
                'date' => '2026-08-26',
                'status' => ValuationSheet::STATUS_SUBMITTED,
                'input' => [
                    'actual_b2b_count' => 0,
                    'actual_b2c_count' => 12,
                    'target_b2b_count' => 15,
                    'target_b2c_count' => 20,
                    'target_count_total' => 35,
                    'drivers_score' => 100,
                    'sales_online' => 70,
                    'sales_social' => 50,
                    'sales_customer_care' => 80,
                    'sales_outdoor' => 70,
                ],
            ])
            ->assertRedirect();

        $sheet = ValuationSheet::query()->where('module', 'product')->firstOrFail();
        $this->assertEquals(35, $sheet->input['target_count_total']);
        $this->assertSame([], $sheet->input['drivers']);
        $this->assertEqualsWithDelta(12 / 35, $sheet->result['count_ratio'], 0.000001);
        $this->assertEqualsWithDelta(45.0, $sheet->result['sales_average'], 0.01);

        $this->withSession(['locale' => 'en'])->actingAs($employee)
            ->get(route('valuation.sheets.show', ['module' => 'product', 'sheet' => $sheet]))
            ->assertOk()
            ->assertSee('34.29%', false)
            ->assertSee('60.00%', false)
            ->assertSee(__('valuation.mohra.sections.marketing'), false);
    }

    public function test_manager_sees_every_employees_sheets_on_service_home_and_index(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-19 12:00:00', config('app.timezone')));
        $manager = User::factory()->manager()->create();
        $hamad = User::factory()->employee()->create(['name_en' => 'Hamad Ali', 'name_ar' => 'حمد علي']);
        $sara = User::factory()->employee()->create(['name_en' => 'Sara Noor', 'name_ar' => 'سارة نور']);

        foreach ([$hamad, $sara] as $i => $user) {
            ValuationAssignment::query()->create([
                'user_id' => $user->id,
                'assigned_by' => $manager->id,
                'module' => 'service',
            ]);
            ValuationSheet::query()->create([
                'user_id' => $user->id,
                'module' => 'service',
                'date' => '2026-08-1'.($i + 8),
                'status' => ValuationSheet::STATUS_SUBMITTED,
                'input' => ['actual_b2b_amount' => 50],
                'result' => ['actual_amount_total' => 50, 'kpi_label' => 'gain'],
            ]);
        }

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('portal.service'))
            ->assertOk()
            ->assertSee(__('valuation.month_sheets'), false)
            ->assertSee('data-table-filter-root', false)
            ->assertSee('data-table-search', false)
            ->assertSee('data-table-filter="employee"', false)
            ->assertSee('data-table-filter="date"', false)
            ->assertSee('data-filter-employee="'.$hamad->id.'"', false)
            ->assertSee('Hamad Ali', false)
            ->assertSee('Sara Noor', false)
            ->assertSee(route('admin.employees.edit', $hamad), false)
            ->assertSee(route('admin.employees.edit', $sara), false);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('valuation.sheets.index', 'service'))
            ->assertOk()
            ->assertSee('Hamad Ali', false)
            ->assertSee('Sara Noor', false)
            ->assertSee('data-table-filter="employee"', false)
            ->assertSee('data-table-filter="date"', false)
            ->assertSee(route('admin.employees.edit', $hamad), false)
            ->assertSee(__('valuation.stats.hint', ['count' => 2]), false);
    }

    public function test_validation_errors_are_shown_on_the_form(): void
    {
        $manager = User::factory()->manager()->create();
        $hamad = User::factory()->employee()->create();
        ValuationAssignment::query()->create([
            'user_id' => $hamad->id,
            'assigned_by' => $manager->id,
            'module' => 'service',
        ]);

        app()->setLocale('en');

        $this->actingAs($hamad)
            ->from(route('valuation.sheets.form', 'service'))
            ->post(route('valuation.sheets.store', 'service'), [
                'date' => '2026-08-19',
                'status' => ValuationSheet::STATUS_DRAFT,
                'input' => [
                    'laundry_king_score' => 150,
                    'drivers' => [
                        ['user_id' => '', 'name' => '', 'orders' => 12, 'score' => '', 'notes' => ''],
                    ],
                ],
            ])
            ->assertRedirect(route('valuation.sheets.form', [
                'module' => 'service',
                'date' => '2026-08-19',
            ]))
            ->assertSessionHasErrors(['input.laundry_king_score', 'input.drivers.0.user_id', 'input.drivers.0.name']);

        $this->actingAs($hamad)
            ->withSession(['locale' => 'en'])
            ->get(route('valuation.sheets.form', ['module' => 'service', 'date' => '2026-08-19']))
            ->assertOk()
            ->assertSee(__('general.form_errors'), false)
            ->assertSee(__('valuation.errors.driver_identity', ['n' => 1]), false)
            ->assertSee('hp-toast--danger', false)
            ->assertSee('data-hp-toast', false)
            ->assertDontSee('id="valuation-form-errors"', false)
            ->assertDontSee('id="validation-errors"', false);

        $this->assertSame(0, ValuationSheet::query()->count());
    }

    public function test_form_survives_failed_submit_with_empty_date(): void
    {
        $manager = User::factory()->manager()->create();
        $hamad = User::factory()->employee()->create();
        ValuationAssignment::query()->create([
            'user_id' => $hamad->id,
            'assigned_by' => $manager->id,
            'module' => 'product',
        ]);

        $this->actingAs($hamad)
            ->from(route('valuation.sheets.form', ['module' => 'product', 'date' => '2026-09-07']))
            ->post(route('valuation.sheets.store', 'product'), [
                'date' => '',
                'status' => ValuationSheet::STATUS_DRAFT,
                'input' => ['laundry_mart_score' => '80'],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('date');

        $this->actingAs($hamad)
            ->get(route('valuation.sheets.form', ['module' => 'product', 'date' => '2026-09-07']))
            ->assertOk()
            ->assertSee('hp-toast--danger', false);
    }
}
