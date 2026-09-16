<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\User;
use App\Services\Employees\TeamMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private ?User $leader = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
    }

    private function defaultLeader(): User
    {
        return $this->leader ??= User::factory()->teamLeader()->create();
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
            'team_leader_ids' => [$this->defaultLeader()->id],
            'default_shifts' => [
                [
                    'shift_type' => 'FULL_TIME',
                    'start' => '10:00',
                    'end' => '18:00',
                ],
                [
                    'shift_type' => '',
                    'start' => '',
                    'end' => '',
                ],
            ],
            'status' => 'ACTIVE',
            'base_salary' => 5000,
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
            ->post(route('admin.employees.store'), $this->validPayload(['team_leader_ids' => [$leader->id]]))
            ->assertRedirect();

        $employee = User::where('email', 'new.employee@hrpro.test')->firstOrFail();

        $this->assertTrue($employee->isEmployee());
        $this->assertTrue($employee->teamLeader->is($leader));
        $this->assertSame('10:00', $employee->work_start_time);
        $this->assertSame('18:00', $employee->work_end_time);
        $this->assertSame('موظف', $employee->job_title_ar);
        $this->assertSame('Employee', $employee->job_title_en);
        $this->assertDatabaseHas('employee_profiles', ['user_id' => $employee->id, 'employee_code' => 'EMP-001']);
        $this->assertDatabaseHas('contracts', ['user_id' => $employee->id, 'is_active' => true, 'contract_type' => 'سنة']);
        $this->assertDatabaseHas('employee_qualifications', ['user_id' => $employee->id, 'degree' => 'BSc']);
        $this->assertDatabaseHas('emergency_contacts', ['user_id' => $employee->id, 'name' => 'Ahmed']);
    }

    public function test_store_rejects_shift_window_over_sixteen_hours(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->validPayload([
                'default_shifts' => [
                    [
                        'shift_type' => 'FULL_TIME',
                        'start' => '08:00',
                        'end' => '01:00',
                    ],
                ],
            ]))
            ->assertSessionHasErrors('default_shifts.0.end');

        $this->assertDatabaseMissing('users', ['email' => 'new.employee@hrpro.test']);
    }

    public function test_store_allows_online_full_sixteen_hour_overnight_window(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->validPayload([
                'default_shifts' => [
                    [
                        'shift_type' => 'ONLINE_FULL',
                        'start' => '09:00',
                        'end' => '01:00',
                    ],
                ],
            ]))
            ->assertRedirect();

        $employee = User::where('email', 'new.employee@hrpro.test')->firstOrFail();
        $this->assertSame('09:00', $employee->work_start_time);
        $this->assertSame('01:00', $employee->work_end_time);
        $this->assertSame(16.0, $employee->plannedHoursFor(Attendance::SHIFT_ONLINE_FULL));
    }

    public function test_store_allows_true_overnight_eight_hour_window(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->validPayload([
                'default_shifts' => [
                    [
                        'shift_type' => 'FULL_TIME',
                        'start' => '22:00',
                        'end' => '06:00',
                    ],
                ],
            ]))
            ->assertRedirect();

        $employee = User::where('email', 'new.employee@hrpro.test')->firstOrFail();
        $this->assertSame('22:00', $employee->work_start_time);
        $this->assertSame('06:00', $employee->work_end_time);
    }

    public function test_store_allows_evening_overnight_grace_window_22_to_10(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->validPayload([
                'default_shifts' => [
                    [
                        'shift_type' => 'FULL_TIME',
                        'start' => '22:00',
                        'end' => '10:00',
                    ],
                ],
            ]))
            ->assertRedirect();

        $employee = User::where('email', 'new.employee@hrpro.test')->firstOrFail();
        $this->assertSame('22:00', $employee->work_start_time);
        $this->assertSame('10:00', $employee->work_end_time);
        $this->assertSame(12.0, $employee->plannedHoursFor(Attendance::SHIFT_FULL_TIME));
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

    public function test_create_form_salary_fields_accept_plain_amounts(): void
    {
        $html = $this->actingAs($this->manager)
            ->get(route('admin.employees.create'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<input\s+type="text"\s+inputmode="decimal"[^>]*name="base_salary"/s',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input\s+type="number"[^>]*name="base_salary"/s',
            $html,
        );
    }

    public function test_store_accepts_four_digit_salary(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->validPayload([
                'base_salary' => 5500,
            ]))
            ->assertRedirect();

        $this->assertSame('5500.00', User::where('email', 'new.employee@hrpro.test')->value('base_salary'));
    }

    public function test_store_accepts_salary_with_thousands_separator(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->validPayload([
                'base_salary' => '5,500',
            ]))
            ->assertRedirect();

        $this->assertSame('5500.00', User::where('email', 'new.employee@hrpro.test')->value('base_salary'));
    }

    public function test_store_accepts_salary_in_arabic_indic_digits(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->validPayload([
                'base_salary' => '٥٥٠٠',
            ]))
            ->assertRedirect();

        $this->assertSame('5500.00', User::where('email', 'new.employee@hrpro.test')->value('base_salary'));
    }

    public function test_store_validation_rejects_duplicate_email(): void
    {
        User::factory()->employee()->create(['email' => 'new.employee@hrpro.test']);

        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->validPayload())
            ->assertSessionHasErrors('email');
    }

    public function test_manager_creates_employee_with_custom_job_titles(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->validPayload([
                'job_title_ar' => 'أخصائي خدمة عملاء',
                'job_title_en' => 'Customer Service Agent',
            ]))
            ->assertRedirect();

        $employee = User::where('email', 'new.employee@hrpro.test')->firstOrFail();
        $this->assertSame('أخصائي خدمة عملاء', $employee->job_title_ar);
        $this->assertSame('Customer Service Agent', $employee->job_title_en);
    }

    public function test_create_form_includes_job_title_fields(): void
    {
        $this->actingAs($this->manager)
            ->get(route('admin.employees.create'))
            ->assertOk()
            ->assertSee('name="job_title_ar"', false)
            ->assertSee('name="job_title_en"', false)
            ->assertSee('name="team_leader_ids[0]"', false)
            ->assertSee('name="team_leader_ids[1]"', false)
            ->assertSee(__('employees.fields.job_title_ar'))
            ->assertSee(__('employees.fields.job_title_en'))
            ->assertSee(__('employees.team_leader_1'))
            ->assertSee(__('employees.team_leader_2'))
            ->assertSee(__('employees.team_leader_1_hint'))
            ->assertSee(__('employees.team_leader_2_hint'));
    }

    public function test_index_shows_and_searches_job_title(): void
    {
        $agent = User::factory()->employee()->create([
            'name_en' => 'Title Agent',
            'job_title_en' => 'Customer Service Agent',
            'job_title_ar' => 'أخصائي خدمة عملاء',
        ]);
        $agent->profile()->create(['employee_code' => 'EMP-T1']);
        $other = User::factory()->employee()->create([
            'name_en' => 'Other Person',
            'job_title_en' => 'Sales Representative',
            'job_title_ar' => 'مندوب مبيعات',
        ]);
        $other->profile()->create(['employee_code' => 'EMP-T2']);

        $this->withSession(['locale' => 'en'])->actingAs($this->manager)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee(__('employees.fields.job_title'))
            ->assertSee('Customer Service Agent')
            ->assertSee('Sales Representative');

        $this->withSession(['locale' => 'en'])->actingAs($this->manager)
            ->get(route('admin.employees.index', ['q' => 'Customer Service']))
            ->assertOk()
            ->assertSee('Title Agent')
            ->assertDontSee('Other Person');
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
            'job_title_ar' => 'مشرف مبيعات',
            'job_title_en' => 'Sales Supervisor',
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
        $this->assertSame('مشرف مبيعات', $employee->job_title_ar);
        $this->assertSame('Sales Supervisor', $employee->job_title_en);
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

        $this->assertNull(User::where('email', 'new.employee@hrpro.test')->first());
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

    public function test_index_lists_each_default_shift_with_its_window(): void
    {
        $employee = User::factory()->employee()->create([
            'name_en' => 'Two Shift Person',
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_FULL_TIME, 'start' => '10:00', 'end' => '18:00'],
                ['type' => Attendance::SHIFT_ONLINE_HALF, 'start' => '18:00', 'end' => '22:00'],
            ],
            'shift_type_default' => Attendance::SHIFT_FULL_TIME,
        ]);
        $employee->profile()->create(['employee_code' => 'EMP-717091']);

        $html = $this->withSession(['locale' => 'en'])->actingAs($this->manager)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee('Two Shift Person')
            ->assertSee(__('employees.shifts.FULL_TIME'))
            ->assertSee(__('employees.shifts.ONLINE_HALF'))
            ->assertSee('10:00–18:00', false)
            ->assertSee('18:00–22:00', false)
            ->getContent();

        $this->assertStringContainsString('EMP-717091', $html);
    }

    public function test_form_exposes_two_default_shift_rows_like_assign(): void
    {
        $this->actingAs($this->manager)
            ->get(route('admin.employees.create'))
            ->assertOk()
            ->assertSee('data-default-shifts', false)
            ->assertSee('data-shift-times', false)
            ->assertSee('name="default_shifts[0][shift_type]"', false)
            ->assertSee('name="default_shifts[1][shift_type]"', false)
            ->assertSee('name="default_shifts[0][start]"', false)
            ->assertSee('name="default_shifts[1][end]"', false)
            ->assertSee('name="default_shifts[2][shift_type]"', false)
            ->assertSee('data-default-shift-hours', false)
            ->assertSee(__('employees.fields.shift_hours'), false)
            ->assertSee(__('employees.shifts.ONLINE_FULL'), false)
            ->assertSee('&quot;ONLINE_HALF&quot;:[&quot;06:00&quot;,&quot;10:00&quot;]', false)
            ->assertSee('&quot;ONLINE_FULL&quot;:[&quot;10:00&quot;,&quot;22:00&quot;]', false);

        $employee = User::factory()->employee()->create();

        $this->actingAs($this->manager)
            ->get(route('admin.employees.edit', $employee))
            ->assertOk()
            ->assertSee('data-shift-times', false);

        $overnight = User::factory()->employee()->create([
            'shift_type_default' => Attendance::SHIFT_ONLINE_FULL,
            'shift_types_default' => [
                ['type' => Attendance::SHIFT_ONLINE_FULL, 'start' => '09:00', 'end' => '01:00'],
            ],
            'work_start_time' => '09:00',
            'work_end_time' => '01:00',
        ]);

        $this->actingAs($this->manager)
            ->get(route('admin.employees.edit', $overnight))
            ->assertOk()
            ->assertSee('data-default-shift-hours', false)
            ->assertSee('16.00h', false);
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

    public function test_manager_creates_employee_with_two_team_leaders(): void
    {
        $first = User::factory()->teamLeader()->create(['name_en' => 'Leader Alpha']);
        $second = User::factory()->teamLeader()->create(['name_en' => 'Leader Beta']);

        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->validPayload([
                'team_leader_ids' => [$first->id, $second->id],
            ]))
            ->assertRedirect();

        $employee = User::where('email', 'new.employee@hrpro.test')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $employee->teamLeaders()->pluck('users.id')->all(),
        );
    }

    public function test_store_requires_first_team_leader_for_employee(): void
    {
        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->validPayload([
                'team_leader_ids' => [],
            ]))
            ->assertSessionHasErrors('team_leader_ids.0');
    }

    public function test_store_rejects_the_same_team_leader_twice(): void
    {
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->validPayload([
                'team_leader_ids' => [$leader->id, $leader->id],
            ]))
            ->assertSessionHasErrors('team_leader_ids.1');
    }

    public function test_employee_form_script_hides_selected_leader_from_the_other_select(): void
    {
        $js = file_get_contents(base_path('resources/js/pages/employee-form.js'));

        $this->assertIsString($js);
        $this->assertStringContainsString('syncExclusiveLeaders', $js);
        $this->assertStringContainsString('option.hidden = taken', $js);
        $this->assertStringContainsString('option.disabled = taken', $js);
        $this->assertStringContainsString("'TEAM_LEADER'", $js);
    }

    public function test_store_ignores_team_leaders_when_role_is_team_leader(): void
    {
        $other = User::factory()->teamLeader()->create();

        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->validPayload([
                'email' => 'new.leader@hrpro.test',
                'name_en' => 'New Leader',
                'role' => User::ROLE_TEAM_LEADER,
                'team_leader_ids' => [$other->id],
            ]))
            ->assertRedirect();

        $created = User::query()->where('email', 'new.leader@hrpro.test')->firstOrFail();
        $this->assertSame(User::ROLE_TEAM_LEADER, $created->role);
        $this->assertSame([], $created->teamLeaders()->pluck('users.id')->all());
    }

    public function test_index_and_profile_show_both_team_leaders(): void
    {
        $first = User::factory()->teamLeader()->create(['name_en' => 'Alpha Lead', 'name_ar' => 'قائد ألفا']);
        $second = User::factory()->teamLeader()->create(['name_en' => 'Beta Lead', 'name_ar' => 'قائد بيتا']);
        $employee = User::factory()->employee()->create(['name_en' => 'Dual Member']);
        $employee->profile()->create(['employee_code' => 'EMP-D2']);
        app(TeamMembershipService::class)
            ->syncLeaders($employee, [$first->id, $second->id]);

        $this->withSession(['locale' => 'en'])->actingAs($this->manager)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee(__('employees.team_leader_1'))
            ->assertSee(__('employees.team_leader_2'))
            ->assertSee('Alpha Lead')
            ->assertSee('Beta Lead');

        $outsider = User::factory()->employee()->create(['name_en' => 'Solo Member']);
        $outsider->profile()->create(['employee_code' => 'EMP-S1']);

        $this->withSession(['locale' => 'en'])->actingAs($this->manager)
            ->get(route('admin.employees.index', ['q' => 'Alpha Lead']))
            ->assertOk()
            ->assertSee('Dual Member')
            ->assertDontSee('Solo Member');

        $this->withSession(['locale' => 'en'])->actingAs($employee)
            ->get(route('employee.profile'))
            ->assertOk()
            ->assertSee('Alpha Lead')
            ->assertSee('Beta Lead');
    }
}
