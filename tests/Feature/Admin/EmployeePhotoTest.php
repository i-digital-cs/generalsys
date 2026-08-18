<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeePhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->manager = User::factory()->manager()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(): array
    {
        return [
            'name_ar' => 'موظف', 'name_en' => 'Employee', 'email' => 'photo@hrpro.test',
            'password' => 'password123', 'role' => 'EMPLOYEE', 'currency' => 'EGP',
            'language_preference' => 'ar', 'shift_type_default' => 'FULL_TIME', 'status' => 'ACTIVE',
            'team_leader_ids' => [User::factory()->teamLeader()->create()->id],
            'base_salary' => 0, 'online_half_day_rate' => 0, 'online_full_day_rate' => 0,
            'overtime_hourly_rate' => 0,
            'profile' => ['employee_code' => 'EMP-PH1'],
            'contract' => [
                'hire_date' => '2024-01-01', 'contract_start' => '2024-01-01',
                'contract_end' => '2026-01-01', 'contract_type' => 'سنة',
            ],
        ];
    }

    public function test_photo_is_stored_on_create_and_served_via_authorized_route(): void
    {
        $photo = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->basePayload() + ['photo' => $photo])
            ->assertRedirect();

        $employee = User::where('email', 'photo@hrpro.test')->firstOrFail();
        $path = $employee->profile->personal_photo_path;

        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        // Manager can view the photo.
        $this->actingAs($this->manager)
            ->get(route('employees.photo', $employee))
            ->assertOk();

        // The owning employee can view it; the avatar renders on edit + self profile.
        $this->actingAs($employee)
            ->get(route('employees.photo', $employee))
            ->assertOk();

        $this->actingAs($employee)
            ->get(route('employee.profile'))
            ->assertOk()
            ->assertSee(route('employees.photo', $employee), false);
    }

    public function test_edit_page_renders_current_avatar(): void
    {
        $employee = User::factory()->employee()->create();
        Storage::disk('local')->put("employee-photos/{$employee->id}/avatar.jpg", 'img');
        $employee->profile()->create([
            'employee_code' => 'EMP-PH2',
            'personal_photo_path' => "employee-photos/{$employee->id}/avatar.jpg",
        ]);

        $this->actingAs($this->manager)
            ->get(route('admin.employees.edit', $employee))
            ->assertOk()
            ->assertSee(route('employees.photo', $employee), false);
    }

    public function test_photo_rejects_disallowed_mime(): void
    {
        $pdf = UploadedFile::fake()->createWithContent('doc.pdf', '%PDF-1.4 fake');

        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->basePayload() + ['photo' => $pdf])
            ->assertSessionHasErrors('photo');

        $this->assertNull(User::where('email', 'photo@hrpro.test')->first());
    }

    public function test_photo_rejects_oversize(): void
    {
        $big = UploadedFile::fake()->create('big.jpg', 3000, 'image/jpeg');

        $this->actingAs($this->manager)
            ->post(route('admin.employees.store'), $this->basePayload() + ['photo' => $big])
            ->assertSessionHasErrors('photo');
    }

    public function test_photo_route_forbidden_for_other_employees_and_tl(): void
    {
        $employee = User::factory()->employee()->create();
        Storage::disk('local')->put("employee-photos/{$employee->id}/avatar.jpg", 'img');
        $employee->profile()->create([
            'employee_code' => 'EMP-PH3',
            'personal_photo_path' => "employee-photos/{$employee->id}/avatar.jpg",
        ]);

        $stranger = User::factory()->employee()->create();
        $this->actingAs($stranger)
            ->get(route('employees.photo', $employee))
            ->assertForbidden();

        $leader = User::factory()->teamLeader()->create();
        $this->actingAs($leader)
            ->get(route('employees.photo', $employee))
            ->assertForbidden();
    }

    public function test_photo_update_replaces_path(): void
    {
        $employee = User::factory()->employee()->create();
        $employee->profile()->create(['employee_code' => 'EMP-PH4']);
        $employee->contracts()->create([
            'hire_date' => '2024-01-01', 'contract_start' => '2024-01-01',
            'contract_end' => '2026-01-01', 'contract_type' => 'سنة', 'is_active' => true,
        ]);

        $payload = $this->basePayload() + ['photo' => UploadedFile::fake()->image('new.png')];
        $payload['email'] = $employee->email;
        $payload['password'] = '';
        $payload['profile'] = ['employee_code' => 'EMP-PH4'];

        $this->actingAs($this->manager)
            ->put(route('admin.employees.update', $employee), $payload)
            ->assertRedirect(route('admin.employees.edit', $employee));

        $path = $employee->profile()->firstOrFail()->personal_photo_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
    }
}
