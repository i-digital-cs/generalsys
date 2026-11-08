<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhMinOrderOverride;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Invoices\GhInvoiceCalculationEngine;
use App\Services\GeneralHouse\Invoices\GhInvoiceIssuanceService;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhMinOrderOverrideType;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhTaxClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceMinOrderTaxUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'general_house.standard_vat_rate' => '5.00',
            'general_house.min_order.surcharge_enabled' => false,
            'general_house.min_order.default_policy' => GhMinOrderPolicy::VALIDATION_ONLY,
            'general_house.min_order_surcharge_tax_class' => null,
        ]);
        foreach (['orders', 'invoices'] as $seq) {
            if (! DB::table('gh_sequences')->where('name', $seq)->exists()) {
                DB::table('gh_sequences')->insert([
                    'name' => $seq,
                    'next_value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * @return array<string, array{0: ?string, 1: bool}>
     */
    public static function overrideMatrix(): array
    {
        return [
            'none_blocks' => [null, false],
            'override_15_allows' => ['15.00', true],
            'override_0_allows' => ['0.00', true],
            'override_25_blocks' => ['25.00', false],
            'override_20_allows' => ['20.00', true],
        ];
    }

    #[DataProvider('overrideMatrix')]
    public function test_zone_30_subtotal_20_override_matrix(?string $override, bool $shouldAllow): void
    {
        $manager = User::factory()->manager()->create();
        $ctx = $this->workingDraft($manager, unitPrice: '20.00', minAmount: '30.00');

        if ($override !== null) {
            $this->actingAs($manager)->put(route('gh.invoices.update', $ctx['invoice']), [
                'draft_version' => $ctx['invoice']->draft_version,
                'invoice_discount_type' => 'NONE',
                'invoice_discount_value' => '0',
                'min_order_effective_amount' => $override,
                'min_order_reason' => 'Matrix override '.$override,
                'lines' => [['offering_id' => $ctx['offering']->id, 'quantity' => 1]],
            ])->assertRedirect();
            $ctx['invoice']->refresh();
            $this->assertSame($override, (string) $ctx['invoice']->min_order_effective_amount);
            $this->assertSame(GhMinOrderOverrideType::OVERRIDE_AMOUNT, $ctx['invoice']->min_order_override_type);
            $this->assertSame('30.00', (string) $ctx['invoice']->min_order_original_amount);
            $this->assertSame(
                'Matrix override '.$override,
                $ctx['invoice']->minOrderOverride?->reason
            );
            $this->assertSame((int) $manager->id, (int) $ctx['invoice']->minOrderOverride?->actor_id);
        }

        if ($shouldAllow) {
            $issued = app(GhInvoiceIssuanceService::class)->issue(
                $ctx['invoice']->fresh(),
                $manager,
                (int) $ctx['invoice']->draft_version
            );
            $this->assertSame('0.00', (string) $issued->min_order_adjustment_amount);
            $this->assertSame(GhOrderStatus::DELIVERY, $ctx['order']->fresh()->status);
        } else {
            $this->expectException(ValidationException::class);
            app(GhInvoiceIssuanceService::class)->issue(
                $ctx['invoice']->fresh(),
                $manager,
                (int) $ctx['invoice']->draft_version
            );
        }
    }

    public function test_subtotal_50_against_zone_30_allows_without_override(): void
    {
        $manager = User::factory()->manager()->create();
        $ctx = $this->workingDraft($manager, unitPrice: '50.00', minAmount: '30.00');

        $html = $this->actingAs($manager)
            ->get(route('gh.invoices.builder', $ctx['invoice']))
            ->assertOk()
            ->assertSee(__('gh.invoices.minimum_order'), false)
            ->assertSee('min_order_effective_amount', false)
            ->assertDontSee('id="min_order_tax_class"', false)
            ->assertDontSee('gh-min-order-warn', false)
            ->getContent();

        $this->assertStringContainsString('name="min_order_effective_amount"', $html);

        $issued = app(GhInvoiceIssuanceService::class)->issue(
            $ctx['invoice'],
            $manager,
            (int) $ctx['invoice']->draft_version
        );
        $this->assertSame(GhOrderStatus::DELIVERY, $ctx['order']->fresh()->status);
        $this->assertSame('0.00', (string) $issued->min_order_adjustment_amount);
        $this->assertSame(0, GhMinOrderOverride::query()->where('invoice_id', $issued->id)->count());
    }

    public function test_zero_override_persists_after_reload_and_is_invoice_scoped(): void
    {
        $manager = User::factory()->manager()->create();
        $a = $this->workingDraft($manager, unitPrice: '20.00', minAmount: '30.00');
        $b = $this->workingDraft($manager, unitPrice: '20.00', minAmount: '30.00');

        $this->actingAs($manager)->put(route('gh.invoices.update', $a['invoice']), [
            'draft_version' => $a['invoice']->draft_version,
            'invoice_discount_type' => 'NONE',
            'invoice_discount_value' => '0',
            'min_order_effective_amount' => '0.00',
            'min_order_reason' => 'Allow zero minimum',
            'lines' => [['offering_id' => $a['offering']->id, 'quantity' => 1]],
        ])->assertRedirect();

        $reloaded = $this->actingAs($manager)
            ->get(route('gh.invoices.builder', $a['invoice']->fresh()))
            ->assertOk()
            ->assertSee('value="0.00"', false);

        $this->assertSame('0.00', (string) $a['invoice']->fresh()->min_order_effective_amount);
        $this->assertSame('30.00', (string) $b['invoice']->fresh()->min_order_effective_amount);
        $this->assertSame('30.00', (string) $a['order']->zone->fresh()->min_order_amount);
        $this->assertNotNull($reloaded);
    }

    public function test_non_manager_cannot_change_minimum(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $ctx = $this->workingDraft($manager, unitPrice: '20.00', minAmount: '30.00');

        $this->actingAs($employee)
            ->put(route('gh.invoices.update', $ctx['invoice']), [
                'draft_version' => $ctx['invoice']->draft_version,
                'invoice_discount_type' => 'NONE',
                'invoice_discount_value' => '0',
                'min_order_effective_amount' => '0.00',
                'min_order_reason' => 'unauthorized',
                'lines' => [['offering_id' => $ctx['offering']->id, 'quantity' => 1]],
            ])
            ->assertForbidden();

        $this->assertSame('30.00', (string) $ctx['invoice']->fresh()->min_order_effective_amount);
    }

    public function test_vat_five_percent_on_taxable_subtotal_eighty_seven(): void
    {
        $calc = app(GhInvoiceCalculationEngine::class)->calculate(
            [[
                'offering_id' => 1,
                'quantity' => 1,
                'unit_price' => '87.00',
                'tax_class' => GhTaxClass::STANDARD_RATED,
                'item_discount_type' => 'NONE',
                'item_discount_value' => '0',
                'product_name_ar' => 'x',
                'treatment_name_ar' => 'y',
            ]],
            [
                'invoice_discount_type' => 'NONE',
                'invoice_discount_value' => '0',
                'min_order_amount' => '15.00',
                'min_order_policy' => GhMinOrderPolicy::VALIDATION_ONLY,
                'min_order_override_type' => GhMinOrderOverrideType::APPLY_DEFAULT,
                'standard_vat_rate' => '5.00',
            ]
        );

        $this->assertSame('4.35', $calc['vat_amount']);
        $this->assertFalse($calc['blocks_issuance']);
        $this->assertSame('0.00', $calc['min_order']['adjustment_amount']);
    }

    public function test_settings_page_shows_configured_vat_for_managers(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)
            ->get(route('gh.settings'))
            ->assertOk()
            ->assertSee('5.00', false)
            ->assertSee(__('gh.settings.save_vat'), false)
            ->assertSee(__('gh.min_order_policy.VALIDATION_ONLY'), false);
    }

    /**
     * @return array{order: GhOrder, invoice: GhInvoice, offering: GhProductOffering}
     */
    private function workingDraft(User $manager, string $unitPrice, string $minAmount): array
    {
        $zone = GhZone::factory()->create([
            'min_order_amount' => $minAmount,
            'min_order_policy' => GhMinOrderPolicy::VALIDATION_ONLY,
        ]);
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $offering = GhProductOffering::factory()->create([
            'standard_price' => $unitPrice,
            'tax_class' => GhTaxClass::STANDARD_RATED,
        ]);
        $order = GhOrder::factory()->create([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => now(),
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'created_by' => $manager->id,
            'pickup_driver_id' => User::factory()->driver()->create()->id,
        ]);

        $invoice = app(GhInvoiceService::class)->createDraftFromOrder($order, $manager);
        $invoice = app(GhInvoiceService::class)->updateDraft($invoice, $manager, [
            'draft_version' => $invoice->draft_version,
            'invoice_discount_type' => 'NONE',
            'invoice_discount_value' => '0',
            'min_order_effective_amount' => $minAmount,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
        ]);

        return ['order' => $order->fresh(['zone']), 'invoice' => $invoice, 'offering' => $offering];
    }
}
