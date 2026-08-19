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
            ->assertSee('Eighteen trips', false)
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
        ])->render();
        $this->assertStringContainsString('37.78%', $pdfHtml);
        $this->assertStringContainsString("font-family: 'Tajawal'", $pdfHtml);
        $this->assertStringContainsString('King on time', $pdfHtml);
        $this->assertStringContainsString('class="stars"', $pdfHtml);
        $this->assertStringNotContainsString('*****', $pdfHtml);
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
            ->assertOk();

        $this->actingAs($hamad)
            ->get(route('valuation.sheets.index', 'product'))
            ->assertOk()
            ->assertSee(__('valuation.errors.not_assigned'), false);
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
            ->getContent();

        $this->assertSame(1, substr_count($html, 'id="sales_spread"'));
        $this->assertSame(1, substr_count($html, 'id="laundry_king_positives"'));
        $this->assertSame(1, substr_count($html, 'id="laundry_king_negatives"'));
    }
}
