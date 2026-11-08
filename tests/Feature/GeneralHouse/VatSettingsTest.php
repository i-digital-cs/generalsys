<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhVatRateAudit;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\Admin\SettingService;
use App\Services\GeneralHouse\Invoices\GhInvoiceCalculationEngine;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Services\GeneralHouse\Settings\GhVatConfigService;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhMinOrderOverrideType;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhTaxClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VatSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'general_house.standard_vat_rate' => '5.00',
            'general_house.min_order.surcharge_enabled' => false,
            'general_house.min_order.default_policy' => GhMinOrderPolicy::VALIDATION_ONLY,
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

    public function test_env_fallback_when_no_database_setting(): void
    {
        config(['general_house.standard_vat_rate' => '0.00']);
        $this->assertSame('0.00', app(GhVatConfigService::class)->standardRate());
        $this->assertFalse(app(GhVatConfigService::class)->hasDatabaseOverride());
    }

    public function test_database_setting_overrides_env(): void
    {
        config(['general_house.standard_vat_rate' => '5.00']);
        app(SettingService::class)->set(SettingService::KEY_GH_STANDARD_VAT_RATE, '7.00');

        $this->assertSame('7.00', app(GhVatConfigService::class)->standardRate());
        $this->assertTrue(app(GhVatConfigService::class)->hasDatabaseOverride());
    }

    public function test_manager_can_update_vat_via_settings_http(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->put(route('gh.settings.vat.update'), [
                'standard_vat_rate' => '0.00',
                'reason' => 'TEST zero VAT UAT',
            ])
            ->assertRedirect(route('gh.settings'));

        $this->assertSame('0.00', app(GhVatConfigService::class)->standardRate());
        $this->assertDatabaseHas('settings', [
            'key' => SettingService::KEY_GH_STANDARD_VAT_RATE,
            'value' => '0.00',
        ]);
        $this->assertSame(1, GhVatRateAudit::query()->count());
        $audit = GhVatRateAudit::query()->first();
        $this->assertSame($manager->id, $audit->actor_id);
        $this->assertSame('5.00', (string) $audit->old_rate);
        $this->assertSame('0.00', (string) $audit->new_rate);
        $this->assertSame('TEST zero VAT UAT', $audit->reason);
    }

    public function test_non_manager_cannot_update_vat(): void
    {
        $driver = User::factory()->driver()->create();

        $this->actingAs($driver)
            ->put(route('gh.settings.vat.update'), [
                'standard_vat_rate' => '7.00',
                'reason' => 'Should be forbidden',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('settings', [
            'key' => SettingService::KEY_GH_STANDARD_VAT_RATE,
        ]);
        $this->assertSame(0, GhVatRateAudit::query()->count());
    }

    public function test_new_draft_stamps_current_configured_rate(): void
    {
        $manager = User::factory()->manager()->create();
        app(GhVatConfigService::class)->updateStandardRate($manager, '0.00', 'Stamp zero');

        $invoice = $this->emptyWorkingDraft($manager);
        $this->assertSame('0.00', (string) $invoice->standard_vat_rate);

        app(GhVatConfigService::class)->updateStandardRate($manager, '7.00', 'Stamp seven');
        $other = $this->emptyWorkingDraft($manager);
        $this->assertSame('7.00', (string) $other->standard_vat_rate);
    }

    public function test_existing_draft_keeps_stamped_rate_when_global_changes(): void
    {
        $manager = User::factory()->manager()->create();
        app(GhVatConfigService::class)->updateStandardRate($manager, '5.00', 'Initial five');

        $ctx = $this->draftWithLine($manager, '100.00', GhTaxClass::STANDARD_RATED);
        $this->assertSame('5.00', (string) $ctx['invoice']->standard_vat_rate);
        $this->assertSame('5.00', (string) $ctx['invoice']->vat_amount);
        $this->assertSame('105.00', (string) $ctx['invoice']->total_payable);

        app(GhVatConfigService::class)->updateStandardRate($manager, '0.00', 'Global to zero');

        $updated = app(GhInvoiceService::class)->updateDraft($ctx['invoice'], $manager, [
            'draft_version' => $ctx['invoice']->draft_version,
            'invoice_discount_type' => 'NONE',
            'invoice_discount_value' => '0',
            'lines' => [['offering_id' => $ctx['offering']->id, 'quantity' => 1]],
        ]);

        $this->assertSame('5.00', (string) $updated->standard_vat_rate);
        $this->assertSame('5.00', (string) $updated->vat_amount);
        $this->assertSame('105.00', (string) $updated->total_payable);
        $this->assertSame('0.00', app(GhVatConfigService::class)->standardRate());
    }

    public function test_zero_and_seven_percent_on_new_drafts(): void
    {
        $manager = User::factory()->manager()->create();

        app(GhVatConfigService::class)->updateStandardRate($manager, '0.00', 'UAT zero');
        $zero = $this->draftWithLine($manager, '100.00', GhTaxClass::STANDARD_RATED);
        $this->assertSame('0.00', (string) $zero['invoice']->vat_amount);
        $this->assertSame('100.00', (string) $zero['invoice']->total_payable);

        app(GhVatConfigService::class)->updateStandardRate($manager, '7.00', 'UAT seven');
        $seven = $this->draftWithLine($manager, '100.00', GhTaxClass::STANDARD_RATED);
        $this->assertSame('7.00', (string) $seven['invoice']->vat_amount);
        $this->assertSame('107.00', (string) $seven['invoice']->total_payable);
    }

    public function test_zero_rated_and_exempt_unaffected_by_standard_rate(): void
    {
        $calc = app(GhInvoiceCalculationEngine::class)->calculate(
            [
                [
                    'offering_id' => 1,
                    'quantity' => 1,
                    'unit_price' => '50.00',
                    'tax_class' => GhTaxClass::ZERO_RATED,
                    'item_discount_type' => 'NONE',
                    'item_discount_value' => '0',
                    'product_name_ar' => 'z',
                    'treatment_name_ar' => 't',
                ],
                [
                    'offering_id' => 2,
                    'quantity' => 1,
                    'unit_price' => '50.00',
                    'tax_class' => GhTaxClass::EXEMPT,
                    'item_discount_type' => 'NONE',
                    'item_discount_value' => '0',
                    'product_name_ar' => 'e',
                    'treatment_name_ar' => 't',
                ],
            ],
            [
                'invoice_discount_type' => 'NONE',
                'invoice_discount_value' => '0',
                'min_order_amount' => '0',
                'min_order_policy' => GhMinOrderPolicy::VALIDATION_ONLY,
                'min_order_override_type' => GhMinOrderOverrideType::APPLY_DEFAULT,
                'standard_vat_rate' => '7.00',
            ]
        );

        $this->assertSame('0.00', $calc['vat_amount']);
        $this->assertSame('100.00', $calc['total_payable']);
    }

    public function test_settings_page_shows_editable_vat_form(): void
    {
        $manager = User::factory()->manager()->create();
        app(SettingService::class)->set(SettingService::KEY_GH_STANDARD_VAT_RATE, '5.00');

        $this->actingAs($manager)
            ->get(route('gh.settings'))
            ->assertOk()
            ->assertSee(__('gh.settings.vat_section'), false)
            ->assertSee('name="standard_vat_rate"', false)
            ->assertSee('name="reason"', false)
            ->assertSee('5.00', false);
    }

    public function test_rejects_out_of_range_vat(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->from(route('gh.settings'))
            ->put(route('gh.settings.vat.update'), [
                'standard_vat_rate' => '150',
                'reason' => 'Too high',
            ])
            ->assertRedirect(route('gh.settings'))
            ->assertSessionHasErrors('standard_vat_rate');
    }

    private function emptyWorkingDraft(User $manager): GhInvoice
    {
        $zone = GhZone::factory()->create([
            'min_order_amount' => '0.00',
            'min_order_policy' => GhMinOrderPolicy::VALIDATION_ONLY,
        ]);
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
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

        return app(GhInvoiceService::class)->createDraftFromOrder($order, $manager);
    }

    /**
     * @return array{invoice: GhInvoice, offering: GhProductOffering}
     */
    private function draftWithLine(User $manager, string $unitPrice, string $taxClass): array
    {
        $invoice = $this->emptyWorkingDraft($manager);
        $offering = GhProductOffering::factory()->create([
            'standard_price' => $unitPrice,
            'tax_class' => $taxClass,
        ]);

        $invoice = app(GhInvoiceService::class)->updateDraft($invoice, $manager, [
            'draft_version' => $invoice->draft_version,
            'invoice_discount_type' => 'NONE',
            'invoice_discount_value' => '0',
            'min_order_effective_amount' => '0.00',
            'min_order_reason' => 'VAT test disposable',
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
        ]);

        return ['invoice' => $invoice, 'offering' => $offering];
    }
}
