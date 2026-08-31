<?php

namespace Tests\Feature\Gross;

use App\Models\Gross\GrossCustomer;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filters_by_name_phone_or_address(): void
    {
        $manager = User::factory()->manager()->create();
        GrossCustomer::query()->create(['name' => 'Ahmed Ali', 'phone' => '01001110000', 'address' => 'Cairo']);
        GrossCustomer::query()->create(['name' => 'Sara Hassan', 'phone' => '01112223333', 'address' => 'Giza']);

        $this->actingAs($manager)
            ->get(route('gross.customers.index', ['q' => 'Sara']))
            ->assertOk()
            ->assertSee('Sara Hassan', false)
            ->assertDontSee('01001110000', false);

        $this->actingAs($manager)
            ->get(route('gross.customers.index', ['q' => '0100111']))
            ->assertOk()
            ->assertSee('Ahmed Ali', false)
            ->assertDontSee('01112223333', false);
    }

    public function test_can_update_customer_from_index(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = GrossCustomer::query()->create([
            'name' => 'Old Name',
            'phone' => '01000000001',
            'address' => 'Old Addr',
        ]);

        $this->actingAs($manager)
            ->put(route('gross.customers.update', $customer), [
                'name' => 'New Name',
                'phone' => '01000000002',
                'address' => 'New Addr',
            ])
            ->assertRedirect(route('gross.customers.index'));

        $this->assertDatabaseHas('gross_customers', [
            'id' => $customer->id,
            'name' => 'New Name',
            'phone' => '01000000002',
            'address' => 'New Addr',
        ]);
    }

    public function test_can_delete_customer_without_invoices(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = GrossCustomer::query()->create([
            'name' => 'Temp',
            'phone' => '01000000003',
        ]);

        $this->actingAs($manager)
            ->delete(route('gross.customers.destroy', $customer))
            ->assertRedirect(route('gross.customers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('gross_customers', ['id' => $customer->id]);
    }

    public function test_cannot_delete_customer_with_invoices(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = GrossCustomer::query()->create([
            'name' => 'Linked',
            'phone' => '01000000004',
        ]);

        GrossSalesInvoice::query()->create([
            'invoice_number' => 'INV-TEST-1',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'currency' => 'EGP',
            'status' => GrossSalesInvoice::STATUS_DRAFT,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->from(route('gross.customers.index'))
            ->delete(route('gross.customers.destroy', $customer))
            ->assertRedirect(route('gross.customers.index'))
            ->assertSessionHasErrors('customer');

        $this->assertDatabaseHas('gross_customers', ['id' => $customer->id]);
    }

    public function test_index_shows_edit_and_delete_controls(): void
    {
        $manager = User::factory()->manager()->create();
        GrossCustomer::query()->create(['name' => 'Visible', 'phone' => '01000000005']);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.customers.index'))
            ->assertOk()
            ->assertSee('bi-eye', false)
            ->assertSee('bi-pencil', false)
            ->assertSee('bi-trash', false)
            ->assertSee('Search name, phone, or address', false);
    }

    public function test_search_json_returns_matching_customers(): void
    {
        $manager = User::factory()->manager()->create();
        GrossCustomer::query()->create(['name' => 'Sara Hassan', 'phone' => '01112223333', 'address' => 'Giza']);
        GrossCustomer::query()->create(['name' => 'Other', 'phone' => '01000000099', 'address' => 'Cairo']);

        $this->actingAs($manager)
            ->getJson(route('gross.customers.search', ['q' => 'Sara']))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Sara Hassan', 'phone' => '01112223333'])
            ->assertJsonMissing(['name' => 'Other']);
    }

    public function test_create_form_suggests_existing_names(): void
    {
        $manager = User::factory()->manager()->create();
        GrossCustomer::query()->create(['name' => 'Existing Client', 'phone' => '01000000006']);

        $this->withSession(['locale' => 'en'])->actingAs($manager)
            ->get(route('gross.customers.create'))
            ->assertOk()
            ->assertSee('gross-suggest-customer-name', false)
            ->assertSee('Existing Client', false)
            ->assertSee('Start typing to see existing customer names', false);
    }
}
