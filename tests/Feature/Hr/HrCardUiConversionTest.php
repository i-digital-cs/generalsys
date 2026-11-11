<?php

namespace Tests\Feature\Hr;

use App\Models\Advance;
use App\Models\EmployeeProfile;
use App\Models\PayrollRecord;
use App\Models\Permission;
use App\Models\User;
use App\Support\Ui\ListViewMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrCardUiConversionTest extends TestCase
{
    use RefreshDatabase;

    private function enableCards(User $user): void
    {
        ListViewMode::put($user, ListViewMode::CARDS);
    }

    public function test_admin_employees_index_uses_card_shell_with_photo_route(): void
    {
        Storage::fake('local');
        $manager = User::factory()->manager()->create();
        $this->enableCards($manager);
        $employee = User::factory()->employee()->create([
            'name_ar' => 'موظف بطاقة',
            'name_en' => 'Card Employee',
            'language_preference' => 'en',
        ]);
        EmployeeProfile::factory()->create([
            'user_id' => $employee->id,
            'personal_photo_path' => UploadedFile::fake()->image('photo.jpg')->store('employee-photos', 'local'),
        ]);

        $this->actingAs($manager)
            ->withSession(['locale' => 'en'])
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee('data-eval-card-ui="hr-employees"', false)
            ->assertSee('gh-record-card', false)
            ->assertSee(route('employees.photo', $employee), false)
            ->assertSee('Card Employee', false)
            ->assertDontSee('table-hr', false);
    }

    public function test_employee_photo_requires_authorization(): void
    {
        Storage::fake('local');
        $employee = User::factory()->employee()->create();
        $stranger = User::factory()->employee()->create();
        EmployeeProfile::factory()->create([
            'user_id' => $employee->id,
            'personal_photo_path' => UploadedFile::fake()->image('photo.jpg')->store('employee-photos', 'local'),
        ]);

        $this->actingAs($stranger)
            ->get(route('employees.photo', $employee))
            ->assertForbidden();
    }

    public function test_admin_payroll_and_advances_use_card_shell(): void
    {
        $manager = User::factory()->manager()->create();
        $this->enableCards($manager);
        $this->enableCards($manager);
        $employee = User::factory()->employee()->create([
            'name_en' => 'Payroll Card User',
            'name_ar' => 'مستخدم راتب',
        ]);

        PayrollRecord::factory()->create([
            'user_id' => $employee->id,
            'created_by' => $manager->id,
            'month' => (int) now()->month,
            'year' => (int) now()->year,
            'status' => PayrollRecord::STATUS_DRAFT,
        ]);
        Advance::factory()->create(['user_id' => $employee->id]);

        $this->actingAs($manager)
            ->get(route('admin.payroll.index', ['month' => now()->month, 'year' => now()->year]))
            ->assertOk()
            ->assertSee('data-eval-card-ui="hr-payroll"', false)
            ->assertSee('Payroll Card User', false)
            ->assertSee(route('admin.payroll.approve', PayrollRecord::query()->first()), false)
            ->assertDontSee('table-hr', false);

        $this->actingAs($manager)
            ->get(route('admin.advances.index'))
            ->assertOk()
            ->assertSee('data-eval-card-ui="hr-advances"', false)
            ->assertDontSee('table-hr', false);
    }

    public function test_team_leader_cannot_open_payroll_cards(): void
    {
        $leader = User::factory()->teamLeader()->create();

        $this->actingAs($leader)
            ->get(route('admin.payroll.index'))
            ->assertForbidden();
    }

    public function test_approvals_permissions_use_cards_while_gaps_keep_table(): void
    {
        $leader = User::factory()->teamLeader()->create();
        $employee = User::factory()->employee()->create(['team_leader_id' => $leader->id]);

        Permission::factory()->create([
            'user_id' => $employee->id,
            'team_leader_id' => $leader->id,
            'status' => Permission::STATUS_PENDING,
        ]);

        $this->enableCards($leader);

        $this->actingAs($leader)
            ->get(route('team-leader.approvals'))
            ->assertOk()
            ->assertSee('data-eval-card-ui="hr-approvals-permissions"', false)
            ->assertSee('gh-record-card', false)
            ->assertSee(__('attendance_gaps.section'), false)
            ->assertSee(__('attendance_review.overtime.section'), false);
    }

    public function test_gh_offerings_create_form_uses_aligned_grid(): void
    {
        $manager = User::factory()->manager()->create();
        $this->enableCards($manager);

        $this->actingAs($manager)
            ->get(route('gh.offerings.index'))
            ->assertOk()
            ->assertSee('gh-offerings-create', false)
            ->assertSee('gh-offerings-create__hint', false)
            ->assertSee('name="product_id"', false)
            ->assertSee('name="standard_price"', false)
            ->assertSee('name="sort_order"', false);
    }
}
