<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossSupplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filters_by_name_phone_or_address(): void
    {
        $manager = User::factory()->manager()->create();
        GrossSupplier::query()->create(['name' => 'Cairo Textiles', 'phone' => '01001110000', 'address' => 'Cairo']);
        GrossSupplier::query()->create(['name' => 'Alex Fabrics', 'phone' => '01112223333', 'address' => 'Alex']);

        $this->actingAs($manager)
            ->get(route('gross.suppliers.index', ['q' => 'Alex']))
            ->assertOk()
            ->assertSee('Alex Fabrics', false)
            ->assertDontSee('01001110000', false);

        $this->actingAs($manager)
            ->get(route('gross.suppliers.index', ['q' => '0100111']))
            ->assertOk()
            ->assertSee('Cairo Textiles', false)
            ->assertDontSee('01112223333', false);
    }

    public function test_can_create_and_update_supplier(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('gross.suppliers.store'), [
                'name' => 'New Supplier',
                'phone' => '01000000001',
                'address' => 'Giza',
                'notes' => 'Main',
            ])
            ->assertRedirect(route('gross.suppliers.index'));

        $supplier = GrossSupplier::query()->where('phone', '01000000001')->firstOrFail();

        $this->actingAs($manager)
            ->put(route('gross.suppliers.update', $supplier), [
                'name' => 'Updated Supplier',
                'phone' => '01000000002',
                'address' => 'Cairo',
                'notes' => 'Updated',
            ])
            ->assertRedirect(route('gross.suppliers.index'));

        $this->assertDatabaseHas('gross_suppliers', [
            'id' => $supplier->id,
            'name' => 'Updated Supplier',
            'phone' => '01000000002',
            'address' => 'Cairo',
            'notes' => 'Updated',
        ]);
    }

    public function test_can_delete_supplier(): void
    {
        $manager = User::factory()->manager()->create();
        $supplier = GrossSupplier::query()->create([
            'name' => 'Temp',
            'phone' => '01000000003',
        ]);

        $this->actingAs($manager)
            ->delete(route('gross.suppliers.destroy', $supplier))
            ->assertRedirect(route('gross.suppliers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('gross_suppliers', ['id' => $supplier->id]);
    }

    public function test_index_shows_edit_and_delete_controls(): void
    {
        $manager = User::factory()->manager()->create();
        GrossSupplier::query()->create(['name' => 'Visible', 'phone' => '01000000005']);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.suppliers.index'))
            ->assertOk()
            ->assertSee('bi-pencil', false)
            ->assertSee('bi-trash', false)
            ->assertSee('Search name, phone, or address', false);
    }

    public function test_create_form_suggests_existing_names(): void
    {
        $manager = User::factory()->manager()->create();
        GrossSupplier::query()->create(['name' => 'Existing Supplier', 'phone' => '01000000006']);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.suppliers.create'))
            ->assertOk()
            ->assertSee('Existing Supplier', false)
            ->assertSee('gross-suggest-supplier-name', false);
    }
}
