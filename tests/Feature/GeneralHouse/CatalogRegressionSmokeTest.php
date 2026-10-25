<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ensures GH Phase 1 routes do not break existing service portal / valuation entry.
 */
class CatalogRegressionSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_portal_still_loads_for_manager(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('portal.service'))
            ->assertOk();
    }

    public function test_valuation_service_index_still_loads(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('valuation.sheets.index', ['module' => 'service']))
            ->assertOk();
    }
}
