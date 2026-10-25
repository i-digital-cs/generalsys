<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhB2bContract;
use App\Models\GeneralHouse\GhB2bContractOffering;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Catalog\GhPricingResolver;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhContractStatus;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomersZonesContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_crud_b2c_and_b2b_customers(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->post(route('gh.customers.store'), [
            'type' => GhCustomerType::B2C,
            'name_ar' => 'عميل',
            'name_en' => 'Guest',
            'phone' => '0500000001',
            'status' => GhCatalogStatus::ACTIVE,
        ])->assertRedirect();

        $b2c = GhCustomer::query()->where('type', GhCustomerType::B2C)->firstOrFail();

        $this->actingAs($manager)->post(route('gh.customers.store'), [
            'type' => GhCustomerType::B2B,
            'name_ar' => 'شركة',
            'name_en' => 'Corp',
            'tax_registration_number' => 'TRN1',
            'status' => GhCatalogStatus::ACTIVE,
        ])->assertRedirect();

        $this->assertDatabaseHas('gh_customers', ['type' => GhCustomerType::B2B, 'name_en' => 'Corp']);

        $this->actingAs($manager)->put(route('gh.customers.update', $b2c), [
            'type' => GhCustomerType::B2C,
            'name_ar' => 'عميل محدّث',
            'name_en' => 'Guest Updated',
            'status' => GhCatalogStatus::INACTIVE,
        ])->assertRedirect();

        $this->assertDatabaseHas('gh_customers', ['id' => $b2c->id, 'name_en' => 'Guest Updated']);
    }

    public function test_default_address_uniqueness_and_zone_crud(): void
    {
        $manager = User::factory()->manager()->create();
        $zone = GhZone::factory()->create([
            'min_order_policy' => GhMinOrderPolicy::SURCHARGE,
            'min_order_amount' => '30.00',
        ]);
        $customer = GhCustomer::factory()->create();

        $this->actingAs($manager)->post(route('gh.customers.addresses.store', $customer), [
            'zone_id' => $zone->id,
            'label' => 'Home',
            'building' => 'V1',
            'is_default' => true,
        ])->assertRedirect();

        $a1 = GhCustomerAddress::query()->firstOrFail();
        $this->assertTrue($a1->is_default);
        $this->assertSame($customer->id, $a1->default_for_customer_id);

        $this->actingAs($manager)->post(route('gh.customers.addresses.store', $customer), [
            'zone_id' => $zone->id,
            'label' => 'Office',
            'is_default' => true,
        ])->assertRedirect();

        $this->assertSame(1, GhCustomerAddress::query()->where('customer_id', $customer->id)->where('is_default', true)->count());
        $this->assertDatabaseHas('gh_customer_addresses', ['label' => 'Office', 'is_default' => 1]);
        $this->assertDatabaseHas('gh_customer_addresses', ['label' => 'Home', 'is_default' => 0]);

        $this->actingAs($manager)->post(route('gh.zones.store'), [
            'name_ar' => 'ديرة',
            'name_en' => 'Deira',
            'min_order_amount' => '25.00',
            'min_order_policy' => GhMinOrderPolicy::VALIDATION_ONLY,
            'status' => GhCatalogStatus::ACTIVE,
            'sort_order' => 1,
        ])->assertRedirect(route('gh.zones.index'));

        $this->assertDatabaseHas('gh_zones', [
            'name_en' => 'Deira',
            'min_order_policy' => GhMinOrderPolicy::VALIDATION_ONLY,
        ]);
    }

    public function test_contract_overlap_and_offering_pricing_rules(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = GhCustomer::factory()->b2b()->create();
        $offering = GhProductOffering::factory()->create(['standard_price' => '10.00', 'status' => GhCatalogStatus::ACTIVE]);
        $inactiveOffering = GhProductOffering::factory()->inactive()->create(['standard_price' => '9.00']);

        $this->actingAs($manager)->post(route('gh.contracts.store'), [
            'customer_id' => $customer->id,
            'reference' => 'GH-CTR-001',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'status' => GhContractStatus::ACTIVE,
        ])->assertRedirect();

        $contract = GhB2bContract::query()->firstOrFail();

        $this->actingAs($manager)->post(route('gh.contracts.store'), [
            'customer_id' => $customer->id,
            'reference' => 'GH-CTR-002',
            'effective_from' => '2026-06-01',
            'effective_to' => '2026-08-01',
            'status' => GhContractStatus::ACTIVE,
        ])->assertSessionHasErrors('effective_from');

        $this->actingAs($manager)->post(route('gh.contracts.offerings.store', $contract), [
            'offering_id' => $offering->id,
            'contract_price' => '6.00',
            'status' => GhCatalogStatus::ACTIVE,
        ])->assertRedirect();

        $this->assertDatabaseHas('gh_b2b_contract_offerings', [
            'contract_id' => $contract->id,
            'offering_id' => $offering->id,
            'contract_price' => '6.00',
        ]);
        $this->assertDatabaseHas('gh_product_offerings', [
            'id' => $offering->id,
            'standard_price' => '10.00',
        ]);

        $this->actingAs($manager)->post(route('gh.contracts.offerings.store', $contract), [
            'offering_id' => $offering->id,
            'contract_price' => '7.00',
            'status' => GhCatalogStatus::ACTIVE,
        ])->assertSessionHasErrors('offering_id');

        // Contract may reference inactive offering row, but resolver excludes it.
        GhB2bContractOffering::factory()->create([
            'contract_id' => $contract->id,
            'offering_id' => $inactiveOffering->id,
            'contract_price' => '1.00',
            'status' => GhCatalogStatus::ACTIVE,
        ]);

        $resolver = app(GhPricingResolver::class);
        $available = $resolver->availableOfferingsForCustomer($customer, '2026-03-01');
        $this->assertTrue($available->contains(fn ($row) => $row['offering']->id === $offering->id && $row['unit_price'] === '6.00'));
        $this->assertFalse($available->contains(fn ($row) => $row['offering']->id === $inactiveOffering->id));

        $price = $resolver->resolvePrice($customer, $offering->fresh(['product.category', 'treatment']), '2026-03-01');
        $this->assertSame('CONTRACT', $price['source']);
        $this->assertSame('6.00', $price['unit_price']);
    }

    public function test_employee_forbidden_and_b2c_uses_standard_catalog(): void
    {
        $employee = User::factory()->employee()->create();
        $this->actingAs($employee)->get(route('gh.customers.index'))->assertForbidden();

        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $offering = GhProductOffering::factory()->create(['standard_price' => '8.50']);
        $resolver = app(GhPricingResolver::class);
        $available = $resolver->availableOfferingsForCustomer($customer);
        $this->assertTrue($available->contains(fn ($row) => $row['offering']->id === $offering->id && $row['source'] === 'STANDARD'));
    }

    public function test_cannot_delete_customer_with_address_history(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = GhCustomer::factory()->create();
        GhCustomerAddress::factory()->create(['customer_id' => $customer->id, 'zone_id' => GhZone::factory()->create()->id]);

        $this->actingAs($manager)
            ->from(route('gh.customers.index'))
            ->delete(route('gh.customers.destroy', $customer))
            ->assertRedirect()
            ->assertSessionHasErrors('customer');
    }
}
