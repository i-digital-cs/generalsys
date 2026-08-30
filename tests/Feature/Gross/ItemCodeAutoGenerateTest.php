<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossItem;
use App\Models\User;
use App\Services\Gross\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemCodeAutoGenerateTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_assigns_sequential_itm_codes_and_ignores_input_code(): void
    {
        $manager = User::factory()->manager()->create();
        $items = app(ItemService::class);

        $a = $items->create([
            'code' => 'SHOULD-IGNORE',
            'name' => 'First',
            'pieces_per_carton' => 12,
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);
        $b = $items->create([
            'name' => 'Second',
            'pieces_per_carton' => 6,
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $this->assertSame('ITM-00001', $a->code);
        $this->assertSame('ITM-00002', $b->code);
        $this->assertDatabaseMissing('gross_items', ['code' => 'SHOULD-IGNORE']);
    }

    public function test_store_form_does_not_require_code(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('gross.items.store'), [
                'name' => 'Dress Auto',
                'reorder_level' => 5,
                'status' => 'ACTIVE',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('gross_items', [
            'name' => 'Dress Auto',
            'code' => 'ITM-00001',
        ]);
    }

    public function test_update_does_not_change_code(): void
    {
        $manager = User::factory()->manager()->create();
        $items = app(ItemService::class);
        $item = $items->create([
            'name' => 'Keep Code',
            'pieces_per_carton' => 1,
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $updated = $items->update($item, [
            'name' => 'Renamed',
            'code' => 'HACKED',
            'pieces_per_carton' => 1,
            'reorder_level' => 5,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $this->assertSame('ITM-00001', $updated->code);
        $this->assertSame('Renamed', $updated->name);
    }
}
