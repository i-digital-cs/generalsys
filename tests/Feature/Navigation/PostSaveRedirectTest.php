<?php

namespace Tests\Feature\Navigation;

use App\Models\Attendance;
use App\Models\AttendanceDailyScore;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossShippingCompany;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhOrderStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Focused coverage for the global post-save → authorized index policy.
 */
class PostSaveRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_create_and_update_redirect_to_employees_index(): void
    {
        $manager = User::factory()->manager()->create();
        $leader = User::factory()->teamLeader()->create();

        $payload = [
            'name_ar' => 'موظف',
            'name_en' => 'Redirect Emp',
            'email' => 'redirect.emp@hrpro.test',
            'password' => 'password123',
            'role' => 'EMPLOYEE',
            'currency' => 'EGP',
            'language_preference' => 'ar',
            'team_leader_ids' => [$leader->id],
            'default_shifts' => [
                ['shift_type' => 'FULL_TIME', 'start' => '10:00', 'end' => '18:00'],
            ],
            'status' => 'ACTIVE',
            'base_salary' => 5000,
            'profile' => [
                'national_id' => '29501010101999',
                'birth_date' => '1995-01-01',
                'address' => 'Cairo',
                'phone' => '01009998877',
                'gender' => 'MALE',
                'marital_status' => 'SINGLE',
            ],
            'contract' => [
                'hire_date' => '2024-01-01',
                'contract_start' => '2024-01-01',
                'contract_end' => '2026-01-01',
                'contract_type' => 'سنة',
            ],
            'qualifications' => [],
            'contacts' => [],
        ];

        $this->actingAs($manager)
            ->post(route('admin.employees.store'), $payload)
            ->assertRedirect(route('admin.employees.index'))
            ->assertSessionHas('success');

        $employee = User::where('email', 'redirect.emp@hrpro.test')->firstOrFail();

        $payload['email'] = $employee->email;
        $payload['password'] = '';
        $payload['name_en'] = 'Redirect Emp Updated';
        $payload['profile']['employee_code'] = $employee->profile->employee_code;

        $this->actingAs($manager)
            ->put(route('admin.employees.update', $employee), $payload)
            ->assertRedirect(route('admin.employees.index'))
            ->assertSessionHas('success');
    }

    public function test_employee_validation_failure_stays_on_create_form(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->from(route('admin.employees.create'))
            ->post(route('admin.employees.store'), [])
            ->assertRedirect(route('admin.employees.create'))
            ->assertSessionHasErrors();
    }

    public function test_gh_order_create_and_update_redirect_to_orders_index_for_manager(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-25 09:00:00', config('app.timezone')));

        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $zone = GhZone::factory()->create();
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
            'is_default' => true,
        ]);
        $offering = GhProductOffering::factory()->create([
            'status' => GhCatalogStatus::ACTIVE,
            'standard_price' => '10.00',
        ]);

        $this->actingAs($manager)->post(route('gh.orders.store'), [
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 1]],
            'pickup_date' => '2026-10-28',
            'pickup_window_start' => '10:00',
            'pickup_window_end' => '12:00',
            'scheduled_delivery_at' => '2026-10-28 16:00',
            'pickup_driver_id' => $driver->id,
        ])
            ->assertRedirect(route('gh.orders.index'))
            ->assertSessionHas('success');

        $order = GhOrder::query()->firstOrFail();

        $this->actingAs($manager)->put(route('gh.orders.update', $order), [
            'address_id' => $address->id,
            'lines' => [['offering_id' => $offering->id, 'quantity' => 2]],
            'pickup_date' => '2026-10-28',
            'pickup_window_start' => '10:00',
            'pickup_window_end' => '12:00',
            'scheduled_delivery_at' => '2026-10-28 18:00',
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'notes' => 'Updated note',
        ])
            ->assertRedirect(route('gh.orders.index'))
            ->assertSessionHas('success');

        Carbon::setTestNow();
    }

    public function test_driver_cannot_use_admin_order_update_route(): void
    {
        $driver = User::factory()->driver()->create();
        $order = GhOrder::factory()->create(['status' => GhOrderStatus::PICKUP]);

        $this->actingAs($driver)
            ->put(route('gh.orders.update', $order), ['notes' => 'Nope'])
            ->assertForbidden();
    }

    public function test_gross_customer_and_shipping_store_redirect_to_index(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('gross.customers.store'), [
                'name' => 'Redirect Customer',
                'phone' => '01005556666',
                'address' => 'Cairo',
            ])
            ->assertRedirect(route('gross.customers.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('gross_customers', ['name' => 'Redirect Customer']);

        $this->actingAs($manager)
            ->post(route('gross.shipping.store'), [
                'name' => 'Redirect Courier',
                'phone' => '01007778888',
                'status' => GrossShippingCompany::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('gross.shipping.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('gross_shipping_companies', ['name' => 'Redirect Courier']);
    }

    public function test_gross_item_update_redirects_to_items_index(): void
    {
        $manager = User::factory()->manager()->create();
        $item = GrossItem::query()->create([
            'code' => 'ITM-00999',
            'name' => 'Before',
            'reorder_level' => 2,
            'status' => GrossItem::STATUS_ACTIVE,
        ]);

        $this->actingAs($manager)
            ->put(route('gross.items.update', $item), [
                'name' => 'After',
                'reorder_level' => 4,
                'status' => GrossItem::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('gross.items.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('gross_items', ['id' => $item->id, 'name' => 'After']);
    }

    public function test_driver_reorder_json_contract_unchanged(): void
    {
        $driver = User::factory()->driver()->create();

        $this->actingAs($driver)
            ->postJson(route('gh.driver.reorder-pickups'), ['order_ids' => []])
            ->assertStatus(422);
    }

    public function test_team_leader_evaluation_save_redirects_to_list(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create([
            'team_leader_id' => $leader->id,
        ]);

        $day = Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => '2026-08-03',
            'shift_type' => Attendance::SHIFT_FULL_TIME,
            'attendance_score' => 100,
        ]);

        AttendanceDailyScore::factory()->create([
            'attendance_id' => $day->id,
            'team_leader_id' => $leader->id,
            'scored_by' => $leader->id,
            'performance_score' => 90,
            'behavior_score' => 80,
            'productivity_score' => 85,
            'interest_return_score' => 75,
        ]);

        $this->actingAs($leader)
            ->post(route('team-leader.evaluations.store', $employee), [
                'month' => 8,
                'year' => 2026,
                'evaluation_text' => 'Good month.',
            ])
            ->assertRedirect(route('team-leader.evaluations'))
            ->assertSessionHas('success');
    }
}
