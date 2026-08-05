<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name_ar' => 'موظف تجريبي',
            'name_en' => 'Test Employee',
            'email' => 'new.employee@hrpro.test',
            'password' => 'password123',
            'role' => 'EMPLOYEE',
            'currency' => 'EGP',
            'language_preference' => 'ar',
            'team_leader_id' => null,
            'shift_type_default' => 'FULL_TIME',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'status' => 'ACTIVE',
            'base_salary' => 5000,
            'online_half_day_rate' => 100,
            'online_full_day_rate' => 200,
            'overtime_hourly_rate' => 50,
            'profile' => [
                'national_id' => '29501010101010',
                'birth_date' => '1995-01-01',
                'address' => 'Cairo',
                'phone' => '01000000000',
                'gender' => 'MALE',
                'religion' => null,
                'marital_status' => 'SINGLE',
            ],
            'contract' => [
                'hire_date' => '2024-01-01',
                'contract_start' => '2024-01-01',
                'contract_end' => '2026-01-01',
                'contract_type' => 'سنة',
            ],
            'qualifications' => [
                ['degree' => 'BSc', 'specialization' => 'Computer Science', 'graduation_year' => 2016],
            ],
            'contacts' => [
                ['name' => 'Ahmed', 'phone' => '01111111111', 'relationship' => 'Brother'],
            ],
        ], $overrides);
    }

    public function test_manager_creates_employee_with_all_related_records_in_one_transaction(): void
    {
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->validPayload(['team_leader_id' => $leader->id]))
            ->assertRedirect();

        $employee = User::where('email', 'new.employee@hrpro.test')->firstOrFail();

        $this->assertTrue($employee->isEmployee());
        $this->assertTrue($employee->teamLeader->is($leader));
        $this->assertSame('10:00', $employee->work_start_time);
        $this->assertSame('18:00', $employee->work_end_time);
        $this->assertDatabaseHas('employee_profiles', ['user_id' => $employee->id, 'employee_code' => 'EMP-001']);
        $this->assertDatabaseHas('contracts', ['user_id' => $employee->id, 'is_active' => true, 'contract_type' => 'سنة']);
        $this->assertDatabaseHas('employee_qualifications', ['user_id' => $employee->id, 'degree' => 'BSc']);
        $this->assertDatabaseHas('emergency_contacts', ['user_id' => $employee->id, 'name' => 'Ahmed']);
    }

    public function test_employee_code_is_auto_generated_sequentially(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->validPayload())
            ->assertRedirect();

        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->validPayload([
                'email' => 'second.employee@hrpro.test',
                'name_en' => 'Second Employee',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('employee_profiles', ['employee_code' => 'EMP-001']);
        $this->assertDatabaseHas('employee_profiles', ['employee_code' => 'EMP-002']);
    }

    public function test_create_form_previews_next_employee_code(): void
    {
        $existing = User::factory()->employee()->create();
        $existing->profile()->create(['employee_code' => 'EMP-007']);

        $this->actingAs($this->manager)
            ->get(route('admin.employees.create'))
            ->assertOk()
            ->assertSee('EMP-008', false);
    }

    public function test_store_validation_rejects_missing_required_fields(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), [])
            ->assertSessionHasErrors([
                'name_ar', 'name_en', 'email', 'password', 'role', 'currency',
                'status', 'base_salary',
                'contract.hire_date', 'contract.contract_start',
                'contract.contract_end', 'contract.contract_type',
            ])
            ->assertSessionDoesntHaveErrors('profile.employee_code');

        $this->assertSame(1, User::count());
    }

    public function test_store_validation_rejects_duplicate_email(): void
    {
        User::factory()->employee()->create(['email' => 'new.employee@hrpro.test']);

        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->validPayload())
            ->assertSessionHasErrors('email');
    }

    public function test_manager_updates_employee_and_related_records(): void
    {
        $employee = User::factory()->employee()->create();
        $employee->profile()->create(['employee_code' => 'EMP-200']);
        $employee->contracts()->create([
            'hire_date' => '2023-01-01', 'contract_start' => '2023-01-01',
            'contract_end' => '2025-01-01', 'contract_type' => 'سنة', 'is_active' => true,
        ]);

        $payload = $this->validPayload([
            'email' => $employee->email,
            'password' => '',
            'base_salary' => 7500,
            'profile' => [
                'employee_code' => 'EMP-HACKED', // ignored — code stays system-assigned
                'phone' => '01222222222',
            ],
            'contract' => [
                'hire_date' => '2023-01-01', 'contract_start' => '2023-01-01',
                'contract_end' => '2027-06-30', 'contract_type' => 'سنتان',
            ],
            'qualifications' => [['degree' => 'MBA', 'specialization' => null, 'graduation_year' => 2020]],
        ]);

        $this->actingAs($this->manager)
            ->put(route('admin.employees.update', $employee), $payload)
            ->assertRedirect(route('admin.employees.edit', $employee));

        $employee->refresh();
        $this->assertSame('7500.00', $employee->base_salary);
        $this->assertSame('EMP-200', $employee->profile->employee_code);
        $this->assertSame('2027-06-30', $employee->activeContract->contract_end->toDateString());
        $this->assertSame('سنتان', $employee->activeContract->contract_type);
        $this->assertDatabaseHas('employee_qualifications', ['user_id' => $employee->id, 'degree' => 'MBA']);
        // Password untouched when the field is left blank.
        $this->assertTrue(Hash::check('password', $employee->password));
    }

    public function test_edit_page_keeps_rates_inside_update_form_not_nested_rest_days(): void
    {
        $employee = User::factory()->employee()->create();
        $employee->profile()->create(['employee_code' => 'EMP-NEST']);

        $html = $this->actingAs($this->manager)
            ->get(route('admin.employees.edit', $employee))
            ->assertOk()
            ->getContent();

        $updateAction = route('admin.employees.update', $employee);
        $restAction = route('admin.employees.rest-days.store', $employee);

        $updatePos = strpos($html, 'action="'.e($updateAction).'"');
        if ($updatePos === false) {
            $updatePos = strpos($html, 'action="'.$updateAction.'"');
        }
        $baseSalaryPos = strpos($html, 'name="base_salary"');
        $contractPos = strpos($html, 'name="contract[hire_date]"');
        $restPos = strpos($html, 'action="'.e($restAction).'"');
        if ($restPos === false) {
            $restPos = strpos($html, 'action="'.$restAction.'"');
        }

        $this->assertNotFalse($updatePos);
        $this->assertNotFalse($baseSalaryPos);
        $this->assertNotFalse($contractPos);
        $this->assertNotFalse($restPos);
        $this->assertGreaterThan($updatePos, $baseSalaryPos);
        $this->assertGreaterThan($baseSalaryPos, $restPos);
        // Employee update </form> must close after rates/contract and before rest-days form.
        $closeBeforeRest = strrpos(substr($html, 0, $restPos), '</form>');
        $this->assertNotFalse($closeBeforeRest);
        $this->assertGreaterThan($contractPos, $closeBeforeRest);
        $this->assertGreaterThan($closeBeforeRest, $restPos);
    }

    public function test_store_with_two_qualifications_and_two_contacts(): void
    {
        $payload = $this->validPayload([
            'qualifications' => [
                ['degree' => 'BSc', 'specialization' => 'CS', 'graduation_year' => 2016],
                ['degree' => 'MBA', 'specialization' => 'HR', 'graduation_year' => 2020],
            ],
            'contacts' => [
                ['name' => 'Ahmed', 'phone' => '011', 'relationship' => 'Brother'],
                ['name' => 'Sara', 'phone' => '012', 'relationship' => 'Sister'],
            ],
        ]);

        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $payload)
            ->assertRedirect();

        $employee = User::where('email', 'new.employee@hrpro.test')->firstOrFail();
        $this->assertSame(2, $employee->qualifications()->count());
        $this->assertSame(2, $employee->emergencyContacts()->count());
    }

    public function test_fully_blank_dynamic_rows_are_ignored(): void
    {
        $payload = $this->validPayload([
            'qualifications' => [
                ['degree' => 'BSc', 'specialization' => null, 'graduation_year' => null],
                ['degree' => '', 'specialization' => '', 'graduation_year' => ''],
                ['degree' => null, 'specialization' => null, 'graduation_year' => null],
            ],
            'contacts' => [
                ['name' => '', 'phone' => '', 'relationship' => ''],
                ['name' => 'Ahmed', 'phone' => '011', 'relationship' => null],
            ],
        ]);

        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $payload)
            ->assertRedirect();

        $employee = User::where('email', 'new.employee@hrpro.test')->firstOrFail();
        $this->assertSame(1, $employee->qualifications()->count());
        $this->assertSame(1, $employee->emergencyContacts()->count());
    }

    public function test_create_form_uses_indexed_row_field_names(): void
    {
        // qualifications[][degree] makes PHP split one visual row into three —
        // names must be qualifications[0][degree] (and contacts likewise).
        $this->actingAs($this->manager)
            ->get(route('admin.employees.create'))
            ->assertOk()
            ->assertSee('name="qualifications[0][degree]"', false)
            ->assertSee('name="qualifications[0][specialization]"', false)
            ->assertSee('name="qualifications[0][graduation_year]"', false)
            ->assertSee('name="contacts[0][name]"', false)
            ->assertSee('name="contacts[0][phone]"', false)
            ->assertDontSee('name="qualifications[][degree]"', false)
            ->assertDontSee('name="contacts[][name]"', false);
    }

    public function test_store_succeeds_when_only_blank_placeholder_rows_are_posted(): void
    {
        // Mirrors the create form: unused empty slots must not block save.
        $payload = $this->validPayload([
            'qualifications' => [
                ['degree' => '', 'specialization' => '', 'graduation_year' => ''],
                ['degree' => '', 'specialization' => '', 'graduation_year' => ''],
                ['degree' => '', 'specialization' => '', 'graduation_year' => ''],
            ],
            'contacts' => [
                ['name' => '', 'phone' => '', 'relationship' => ''],
                ['name' => '', 'phone' => '', 'relationship' => ''],
            ],
        ]);

        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $payload)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $employee = User::where('email', 'new.employee@hrpro.test')->firstOrFail();
        $this->assertSame(0, $employee->qualifications()->count());
        $this->assertSame(0, $employee->emergencyContacts()->count());
    }

    public function test_partially_filled_rows_fail_validation(): void
    {
        $payload = $this->validPayload([
            'qualifications' => [
                ['degree' => '', 'specialization' => 'CS', 'graduation_year' => null],
            ],
            'contacts' => [
                ['name' => 'Ahmed', 'phone' => '', 'relationship' => null],
            ],
        ]);

        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $payload)
            ->assertSessionHasErrors(['qualifications.0.degree', 'contacts.0.phone']);

        $this->assertSame(1, User::count());
    }

    public function test_update_replaces_contacts_and_qualifications_rows(): void
    {
        $employee = User::factory()->employee()->create();
        $employee->profile()->create(['employee_code' => 'EMP-300']);
        $employee->contracts()->create([
            'hire_date' => '2023-01-01', 'contract_start' => '2023-01-01',
            'contract_end' => '2025-01-01', 'contract_type' => 'سنة', 'is_active' => true,
        ]);
        $employee->qualifications()->create(['degree' => 'Old Degree']);
        $employee->emergencyContacts()->create(['name' => 'Old Contact', 'phone' => '010']);

        $payload = $this->validPayload([
            'email' => $employee->email,
            'password' => '',
            'profile' => ['employee_code' => 'EMP-300'],
            'contract' => [
                'hire_date' => '2023-01-01', 'contract_start' => '2023-01-01',
                'contract_end' => '2026-01-01', 'contract_type' => 'سنة',
            ],
            'qualifications' => [
                ['degree' => 'New Degree', 'specialization' => null, 'graduation_year' => null],
            ],
            'contacts' => [
                ['name' => 'New Contact', 'phone' => '015', 'relationship' => null],
                ['name' => 'Second Contact', 'phone' => '016', 'relationship' => null],
            ],
        ]);

        $this->actingAs($this->manager)
            ->put(route('admin.employees.update', $employee), $payload)
            ->assertRedirect(route('admin.employees.edit', $employee));

        $this->assertSame(1, $employee->qualifications()->count());
        $this->assertSame('New Degree', $employee->qualifications()->firstOrFail()->degree);
        $this->assertSame(2, $employee->emergencyContacts()->count());
        $this->assertSame(0, $employee->emergencyContacts()->where('name', 'Old Contact')->count());
    }

    public function test_employee_cannot_access_employee_crud(): void
    {
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)
            ->post(route('admin.employees.store'), $this->validPayload())
            ->assertForbidden();
    }

    public function test_team_leader_cannot_access_employee_crud(): void
    {
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($leader)
            ->post(route('admin.employees.store'), $this->validPayload())
            ->assertForbidden();
    }

    public function test_index_searches_by_name_and_code(): void
    {
        $radwa = User::factory()->employee()->create(['name_en' => 'Radwa Adel']);
        $radwa->profile()->create(['employee_code' => 'EMP-R1']);
        $mona = User::factory()->employee()->create(['name_en' => 'Mona Samy']);
        $mona->profile()->create(['employee_code' => 'EMP-M1']);

        $this->withSession(['locale' => 'en'])->actingAs($this->manager)
            ->get(route('admin.employees.index', ['q' => 'Radwa']))
            ->assertOk()
            ->assertSee('Radwa Adel')
            ->assertDontSee('Mona Samy');

        $this->withSession(['locale' => 'en'])->actingAs($this->manager)
            ->get(route('admin.employees.index', ['q' => 'EMP-M1']))
            ->assertOk()
            ->assertSee('Mona Samy')
            ->assertDontSee('Radwa Adel');
    }

    public function test_index_shows_phone_and_shift_type_columns(): void
    {
        $employee = User::factory()->employee()->create([
            'name_en' => 'Column Check',
            'shift_type_default' => 'ONLINE_HALF',
        ]);
        $employee->profile()->create(['employee_code' => 'EMP-C1', 'phone' => '01001234567']);

        $this->withSession(['locale' => 'en'])->actingAs($this->manager)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee('01001234567')
            ->assertSee(__('employees.shifts.ONLINE_HALF'));
    }

    public function test_form_exposes_shift_type_default_time_windows(): void
    {
        $this->actingAs($this->manager)
            ->get(route('admin.employees.create'))
            ->assertOk()
            ->assertSee('data-shift-times', false)
            ->assertSee('&quot;ONLINE_HALF&quot;:[&quot;06:00&quot;,&quot;10:00&quot;]', false);

        $employee = User::factory()->employee()->create();

        $this->actingAs($this->manager)
            ->get(route('admin.employees.edit', $employee))
            ->assertOk()
            ->assertSee('data-shift-times', false);
    }

    public function test_index_filters_by_status_and_paginates(): void
    {
        User::factory()->employee()->count(30)->create();
        User::factory()->employee()->inactive()->create(['name_en' => 'Inactive Person']);

        $this->actingAs($this->manager)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertViewHas('employees', fn ($page) => $page->count() === 25 && $page->total() === 31);

        $this->withSession(['locale' => 'en'])->actingAs($this->manager)
            ->get(route('admin.employees.index', ['status' => 'INACTIVE']))
            ->assertOk()
            ->assertSee('Inactive Person')
            ->assertViewHas('employees', fn ($page) => $page->total() === 1);
    }

    public function test_index_shows_empty_state(): void
    {
        $this->actingAs($this->manager)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee(__('employees.empty_title'));
    }
}
