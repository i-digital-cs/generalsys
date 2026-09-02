<?php

namespace Tests\Feature\TeamLeader;

use App\Models\User;
use App\Models\VacationBalance;
use App\Models\VacationCreditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** TL / Manager vacation credit UI + balance rows. */
class VacationCreditTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = User::factory()->teamLeader()->create([
            'language_preference' => 'en',
        ]);
        $this->employee = User::factory()->employee()->create([
            'team_leader_id' => $this->leader->id,
            'name_en' => 'Credit Team Member',
            'language_preference' => 'en',
        ]);
    }

    public function test_team_leader_sees_vacation_credit_page(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->leader)
            ->get(route('team-leader.vacation-credit'))
            ->assertOk()
            ->assertSee(__('vacations.credit.title'), false)
            ->assertSee('Credit Team Member', false);
    }

    public function test_team_leader_can_add_credit_for_team_member(): void
    {
        $this->actingAs($this->leader)
            ->post(route('team-leader.vacation-credit.store', $this->employee), [
                'amount_days' => 5,
                'reason' => 'Year start',
                'year' => 2026,
            ])
            ->assertRedirect(route('team-leader.vacation-credit'))
            ->assertSessionHas('success');

        $balance = VacationBalance::query()
            ->where('user_id', $this->employee->id)
            ->where('year', 2026)
            ->first();

        $this->assertNotNull($balance);
        $this->assertSame('5.0', $balance->accrued_days);
        $this->assertSame('5.0', $balance->remaining_days);

        $this->assertDatabaseHas('vacation_credit_logs', [
            'added_by' => $this->leader->id,
            'amount_days' => 5,
        ]);
    }

    public function test_manager_can_add_credit(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('team-leader.vacation-credit.store', $this->employee), [
                'amount_days' => 2.5,
            ])
            ->assertRedirect();

        $this->assertSame('2.5', VacationBalance::query()->where('user_id', $this->employee->id)->value('remaining_days'));
    }

    public function test_team_leader_cannot_add_credit_for_other_team(): void
    {
        $otherLeader = User::factory()->teamLeader()->create();
        $otherEmployee = User::factory()->employee()->create([
            'team_leader_id' => $otherLeader->id,
        ]);

        $this->actingAs($this->leader)
            ->post(route('team-leader.vacation-credit.store', $otherEmployee), [
                'amount_days' => 3,
            ])
            ->assertForbidden();

        $this->assertSame(0, VacationCreditLog::query()->count());
    }

    public function test_history_lists_credit_entries(): void
    {
        app()->setLocale('en');

        $this->actingAs($this->leader)
            ->post(route('team-leader.vacation-credit.store', $this->employee), [
                'amount_days' => 1.5,
                'reason' => 'Bonus',
            ]);

        $this->actingAs($this->leader)
            ->get(route('team-leader.vacation-credit.history', $this->employee))
            ->assertOk()
            ->assertSee(__('vacations.credit.history_title', [
                'name' => 'Credit Team Member',
                'year' => (int) now()->format('Y'),
            ]), false)
            ->assertSee('Bonus', false)
            ->assertSee('1.5', false);
    }
}
