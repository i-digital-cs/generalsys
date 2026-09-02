<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossCustomer;
use App\Models\Gross\GrossExpenseType;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossTreasuryAccount;
use App\Models\User;
use App\Services\Gross\GrossDataResetService;
use App\Services\Gross\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrossDataResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_clears_gross_data_and_reseeds_defaults(): void
    {
        $manager = User::factory()->manager()->create();

        app(ItemService::class)->create([
            'name' => 'Shirt',
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        GrossCustomer::query()->create([
            'name' => 'Ali',
            'phone' => '01001234567',
        ]);

        $this->assertGreaterThan(0, GrossItem::query()->count());
        $this->assertGreaterThan(0, GrossCustomer::query()->count());

        app(GrossDataResetService::class)->fresh();

        $this->assertSame(0, GrossItem::query()->count());
        $this->assertSame(0, GrossCustomer::query()->count());
        $this->assertSame(2, GrossTreasuryAccount::query()->count());
        $this->assertGreaterThanOrEqual(3, GrossExpenseType::query()->count());
    }

    public function test_gross_fresh_command_runs(): void
    {
        $this->artisan('gross:fresh', ['--force' => true])
            ->assertSuccessful();
    }
}
