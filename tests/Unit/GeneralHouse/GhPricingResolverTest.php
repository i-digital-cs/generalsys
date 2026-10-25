<?php

namespace Tests\Unit\GeneralHouse;

use App\Models\GeneralHouse\GhB2bContract;
use App\Models\GeneralHouse\GhB2bContractOffering;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhProductOffering;
use App\Services\GeneralHouse\Catalog\GhPricingResolver;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhContractStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GhPricingResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_b2b_without_contract_has_empty_catalog(): void
    {
        $customer = GhCustomer::factory()->b2b()->create();
        GhProductOffering::factory()->create();

        $rows = app(GhPricingResolver::class)->availableOfferingsForCustomer($customer);
        $this->assertCount(0, $rows);
    }

    public function test_expired_contract_does_not_price(): void
    {
        $customer = GhCustomer::factory()->b2b()->create();
        $offering = GhProductOffering::factory()->create(['standard_price' => '12.00']);
        $contract = GhB2bContract::factory()->create([
            'customer_id' => $customer->id,
            'status' => GhContractStatus::ACTIVE,
            'effective_from' => '2025-01-01',
            'effective_to' => '2025-12-31',
        ]);
        GhB2bContractOffering::factory()->create([
            'contract_id' => $contract->id,
            'offering_id' => $offering->id,
            'contract_price' => '4.00',
            'status' => GhCatalogStatus::ACTIVE,
        ]);

        $this->expectException(ValidationException::class);
        app(GhPricingResolver::class)->resolvePrice($customer, $offering->fresh(['product.category', 'treatment']), '2026-06-01');
    }
}
