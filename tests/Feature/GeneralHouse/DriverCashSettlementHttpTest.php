<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhDriverCashBalance;
use App\Models\GeneralHouse\GhDriverCashSettlement;
use App\Models\GeneralHouse\GhDriverCashSettlementAllocation;
use App\Models\GeneralHouse\GhDriverCashSettlementReversal;
use App\Models\User;
use App\Services\GeneralHouse\Payments\GhDriverCashCustodyService;
use App\Services\GeneralHouse\Payments\GhDriverCashSettlementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\GeneralHouse\Concerns\GhDriverCashFixtures;
use Tests\TestCase;

class DriverCashSettlementHttpTest extends TestCase
{
    use GhDriverCashFixtures;
    use RefreshDatabase;

    private User $manager;

    private User $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDriverCash();
        $this->manager = User::factory()->manager()->create(['name_en' => 'Office Manager']);
        $this->driver = User::factory()->driver()->create(['name_en' => 'Cash Driver', 'name_ar' => 'سائق النقد']);
        Model::preventLazyLoading();
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        parent::tearDown();
    }

    public function test_manager_settles_selected_invoices_through_the_form(): void
    {
        $a = $this->collectCash($this->driver, '100.00');
        $b = $this->collectCash($this->driver, '50.00');

        $this->actingAs($this->manager)->get(route('gh.driver-cash.settlements.create', $this->driver))
            ->assertOk()
            ->assertSee($a->reference)
            ->assertSee($b->reference)
            ->assertSee(__('gh.driver_cash.confirm_title'));

        $form = $this->settleForm('70.00', [$a->id => '20.00', $b->id => '50.00']);
        $form['allocations'][999999] = ['selected' => '0'];
        $response = $this->actingAs($this->manager)->post(route('gh.driver-cash.settle', $this->driver), $form);

        $settlement = GhDriverCashSettlement::query()->sole();
        $response->assertRedirect(route('gh.driver-cash.settlements.show', $settlement));
        $this->assertSame(2, GhDriverCashSettlementAllocation::query()->count());
        $this->assertSame('80.00', app(GhDriverCashCustodyService::class)->outstandingFor($this->driver));

        $this->actingAs($this->manager)->get(route('gh.driver-cash.settlements.show', $settlement))
            ->assertOk()
            ->assertSee($settlement->reference)
            ->assertSee($a->reference)
            ->assertSee(__('gh.driver_cash.reverse_title'));
    }

    public function test_mismatch_and_over_allocation_return_field_errors(): void
    {
        $a = $this->collectCash($this->driver, '100.00');

        $this->actingAs($this->manager)
            ->post(route('gh.driver-cash.settle', $this->driver), $this->settleForm('50.00', [$a->id => '40.00']))
            ->assertSessionHasErrors('received_amount');
        $this->actingAs($this->manager)
            ->post(route('gh.driver-cash.settle', $this->driver), $this->settleForm('0', [$a->id => '0']))
            ->assertSessionHasErrors('received_amount');
        $this->actingAs($this->manager)
            ->post(route('gh.driver-cash.settle', $this->driver), $this->settleForm('10.00', [$a->id => '10.005']))
            ->assertSessionHasErrors('allocations.'.$a->id.'.amount');
        $this->actingAs($this->manager)
            ->post(route('gh.driver-cash.settle', $this->driver), ['received_amount' => '10.00', 'idempotency_key' => 'k'] + ['allocations' => [$a->id => ['selected' => '0', 'amount' => '10.00']]])
            ->assertSessionHasErrors('allocations');
        $this->assertSame(0, GhDriverCashSettlement::query()->count());
    }

    public function test_reverse_requires_reason_then_restores_custody(): void
    {
        $a = $this->collectCash($this->driver, '100.00');
        $s = app(GhDriverCashSettlementService::class)->settle($this->driver, $this->manager, $this->settlePayload('100.00', [$a->id => '100.00']));

        $this->actingAs($this->manager)
            ->post(route('gh.driver-cash.settlements.reverse', $s), ['reason' => '', 'idempotency_key' => 'r'])
            ->assertSessionHasErrors('reason');
        $this->assertSame(0, GhDriverCashSettlementReversal::query()->count());

        $this->actingAs($this->manager)
            ->post(route('gh.driver-cash.settlements.reverse', $s), ['reason' => 'Envelope belonged to another driver', 'idempotency_key' => 'r'])
            ->assertRedirect(route('gh.driver-cash.settlements.show', $s));
        $this->assertSame('100.00', app(GhDriverCashCustodyService::class)->outstandingFor($this->driver));

        $this->actingAs($this->manager)->get(route('gh.driver-cash.settlements.show', $s))
            ->assertOk()
            ->assertSee('Envelope belonged to another driver')
            ->assertDontSee(route('gh.driver-cash.settlements.reverse', $s), false);
    }

    public function test_legacy_settlement_page_offers_manual_allocation(): void
    {
        $a = $this->collectCash($this->driver, '100.00');
        $legacy = GhDriverCashSettlement::query()->forceCreate([
            'reference' => 'GH-DCS-LEGACY', 'driver_id' => $this->driver->id, 'amount' => '40.00', 'currency' => 'AED',
            'settled_by' => $this->manager->id, 'settled_at' => now('UTC')->subDay(), 'idempotency_key' => 'legacy',
        ]);
        GhDriverCashBalance::query()->whereKey($this->driver->id)->update(['settled_total' => '40.00', 'outstanding' => '60.00']);

        $this->actingAs($this->manager)->get(route('gh.driver-cash.settlements.show', $legacy))
            ->assertOk()
            ->assertSee(__('gh.driver_cash.legacy_badge'))
            ->assertSee(__('gh.driver_cash.allocate_title'));

        $this->actingAs($this->manager)->post(route('gh.driver-cash.settlements.allocate', $legacy), [
            'idempotency_key' => (string) Str::uuid(),
            'allocations' => [$a->id => ['selected' => '1', 'amount' => '40.00']],
        ])->assertRedirect(route('gh.driver-cash.settlements.show', $legacy));

        $this->assertSame(1, GhDriverCashSettlementAllocation::query()->where('settlement_id', $legacy->id)->count());
        $this->assertSame('60.00', app(GhDriverCashCustodyService::class)->outstandingFor($this->driver));
    }

    public function test_dashboard_tabs_render_within_query_budget_in_both_locales(): void
    {
        $a = $this->collectCash($this->driver, '100.00');
        $this->collectCash($this->driver, '30.00');
        $s = app(GhDriverCashSettlementService::class)->settle($this->driver, $this->manager, $this->settlePayload('40.00', [$a->id => '40.00']));
        app(GhDriverCashSettlementService::class)->reverse($s, $this->manager, ['reason' => 'test', 'idempotency_key' => 'rv']);

        foreach (['en', 'ar'] as $locale) {
            foreach (['collections', 'outstanding', 'settlements', 'adjustments', 'ledger'] as $tab) {
                DB::enableQueryLog();
                DB::flushQueryLog();
                $this->actingAs($this->manager)->withSession(['locale' => $locale])
                    ->get(route('gh.driver-cash.show', [$this->driver, 'tab' => $tab, 'as_of' => now('Asia/Dubai')->toDateString()]))
                    ->assertOk()
                    ->assertSee(__('gh.driver_cash.tab_'.$tab, [], $locale))
                    ->assertSee('Asia/Dubai');
                $this->assertLessThanOrEqual(30, count(DB::getQueryLog()), "{$locale}/{$tab} query budget");
            }
        }

        $this->actingAs($this->manager)->withSession(['locale' => 'en'])
            ->get(route('gh.driver-cash.index', ['q' => 'Cash']))->assertOk()->assertSee('Cash Driver');
        $this->actingAs($this->manager)->withSession(['locale' => 'ar'])
            ->get(route('gh.driver-cash.index', ['q' => 'سائق']))->assertOk()->assertSee('سائق النقد');
        $this->actingAs($this->manager)->get(route('gh.driver-cash.index', ['driver_id' => $this->driver->id]))
            ->assertRedirect(route('gh.driver-cash.show', $this->driver->id));
        $this->actingAs($this->manager)->get(route('gh.driver-cash.settlements.index', ['state' => 'reversed']))
            ->assertOk()->assertSee($s->reference);
    }

    public function test_settlement_time_is_displayed_in_dubai(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 21:30:00', 'UTC'));
        try {
            $a = $this->collectCash($this->driver, '100.00');
            $s = app(GhDriverCashSettlementService::class)->settle($this->driver, $this->manager, $this->settlePayload('100.00', [$a->id => '100.00']));

            $this->actingAs($this->manager)->get(route('gh.driver-cash.settlements.show', $s))
                ->assertOk()->assertSee('2026-11-19 01:30');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_order_and_invoice_pages_show_financial_section_for_manager_only(): void
    {
        $a = $this->collectCash($this->driver, '100.00');
        $s = app(GhDriverCashSettlementService::class)->settle($this->driver, $this->manager, $this->settlePayload('30.00', [$a->id => '30.00']));
        $invoice = $this->invoiceFor($a->order);

        $this->actingAs($this->manager)->get(route('gh.orders.show', $a->order_id))
            ->assertOk()
            ->assertSee(__('gh.order_financial.title'))
            ->assertSee($s->reference)
            ->assertSee(__('gh.cash_settlement_status.PARTIAL'))
            ->assertSee(__('gh.order_financial.event.cash_settled'));

        $this->actingAs($this->manager)->get(route('gh.invoices.show', $invoice))
            ->assertOk()
            ->assertSee(__('gh.invoices.custody_panel'))
            ->assertSee($s->reference);

        $this->actingAs($this->driver)->get(route('gh.orders.show', $a->order_id))
            ->assertDontSee(__('gh.order_financial.title'));
    }

    public function test_invoice_list_renders_settlement_columns_in_both_modes(): void
    {
        $a = $this->collectCash($this->driver, '100.00');
        app(GhDriverCashSettlementService::class)->settle($this->driver, $this->manager, $this->settlePayload('100.00', [$a->id => '100.00']));
        $invoice = $this->invoiceFor($a->order);

        foreach (['legacy', 'cards'] as $mode) {
            $this->actingAs($this->manager)->withSession(['ui.display_mode.'.$this->manager->id => $mode])
                ->get(route('gh.invoices.index', ['settlement_status' => 'SETTLED', 'driver_id' => $this->driver->id]))
                ->assertOk()
                ->assertSee($invoice->number)
                ->assertSee(__('gh.cash_settlement_status.SETTLED'));
            $this->actingAs($this->manager)->withSession(['ui.display_mode.'.$this->manager->id => $mode])
                ->get(route('gh.invoices.index', ['settlement_status' => 'NOT_SETTLED']))
                ->assertOk()
                ->assertDontSee($invoice->number);
        }
        $this->actingAs($this->manager)->get(route('gh.invoices.index', ['settlement_status' => 'BOGUS']))->assertSessionHasErrors('settlement_status');
    }

    public function test_report_page_and_csv_exports(): void
    {
        $a = $this->collectCash($this->driver, '100.00');
        app(GhDriverCashSettlementService::class)->settle($this->driver, $this->manager, $this->settlePayload('25.00', [$a->id => '25.00']));

        $this->actingAs($this->manager)->withSession(['locale' => 'en'])->get(route('gh.reports.driver-cash'))
            ->assertOk()
            ->assertSee(__('gh.reports.driver_cash', [], 'en'))
            ->assertSee('Cash Driver')
            ->assertSee($a->reference);

        foreach (['outstanding' => $a->reference, 'by_driver' => 'Cash Driver', 'by_date' => '25.00', 'by_manager' => 'Office Manager'] as $dataset => $needle) {
            $response = $this->actingAs($this->manager)->get(route('gh.reports.driver-cash', ['export' => $dataset]));
            $response->assertOk();
            $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
            $this->assertStringContainsString($needle, $response->streamedContent());
        }
    }

    public function test_non_managers_are_forbidden_everywhere(): void
    {
        $a = $this->collectCash($this->driver, '100.00');
        $s = app(GhDriverCashSettlementService::class)->settle($this->driver, $this->manager, $this->settlePayload('10.00', [$a->id => '10.00']));

        foreach ([$this->driver, User::factory()->employee()->create(), User::factory()->teamLeader()->create()] as $user) {
            $this->actingAs($user);
            $this->get(route('gh.driver-cash.index'))->assertForbidden();
            $this->get(route('gh.driver-cash.show', $this->driver))->assertForbidden();
            $this->get(route('gh.driver-cash.settlements.create', $this->driver))->assertForbidden();
            $this->get(route('gh.driver-cash.settlements.index'))->assertForbidden();
            $this->get(route('gh.driver-cash.settlements.show', $s))->assertForbidden();
            $this->get(route('gh.reports.driver-cash'))->assertForbidden();
            $this->get(route('gh.reports.driver-cash', ['export' => 'outstanding']))->assertForbidden();
            $this->post(route('gh.driver-cash.settle', $this->driver), $this->settleForm('10.00', [$a->id => '10.00']))->assertForbidden();
            $this->post(route('gh.driver-cash.settlements.reverse', $s), ['reason' => 'x', 'idempotency_key' => 'x'])->assertForbidden();
            $this->post(route('gh.driver-cash.settlements.allocate', $s), ['idempotency_key' => 'x', 'allocations' => [$a->id => ['selected' => '1', 'amount' => '1']]])->assertForbidden();
        }
        $this->assertSame(1, GhDriverCashSettlement::query()->count());
        $this->assertSame(0, GhDriverCashSettlementReversal::query()->count());
    }
}
