<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use App\Services\Admin\SettingService;
use App\Support\Ui\ListViewMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DualListViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_pages_default_to_legacy_tables(): void
    {
        $manager = User::factory()->manager()->create();
        User::factory()->employee()->create([
            'name_ar' => 'صف قديم',
            'name_en' => 'Legacy Row',
        ]);

        $this->actingAs($manager)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee('data-display-mode-toggle', false)
            ->assertSee('data-list-view-mode="legacy"', false)
            ->assertSee('table-hr', false)
            ->assertSee('صف قديم', false)
            ->assertDontSee('data-eval-card-ui="hr-employees"', false)
            ->assertDontSee('list-view-toggle page=', false);
    }

    public function test_global_header_toggle_applies_across_modules(): void
    {
        $manager = User::factory()->manager()->create();
        User::factory()->employee()->create(['name_en' => 'Across Modules']);

        $this->actingAs($manager)
            ->from(route('admin.employees.index'))
            ->post(route('ui.list-view'), ['mode' => ListViewMode::CARDS])
            ->assertRedirect(route('admin.employees.index'));

        $this->actingAs($manager)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee('data-list-view-mode="cards"', false)
            ->assertSee('data-eval-card-ui="hr-employees"', false);

        $this->actingAs($manager)
            ->get(route('gh.customers.index'))
            ->assertOk()
            ->assertSee('data-gh-card-ui="customers"', false)
            ->assertSee('data-list-view-mode="cards"', false);

        // Survives session forget → reload from settings.
        session()->forget('ui.display_mode.'.$manager->id);
        $this->assertSame(ListViewMode::CARDS, ListViewMode::current($manager->fresh()));
    }

    public function test_preferences_are_isolated_per_user(): void
    {
        $manager = User::factory()->manager()->create();
        $other = User::factory()->manager()->create();

        ListViewMode::put($manager, ListViewMode::CARDS);

        $this->assertSame(ListViewMode::CARDS, ListViewMode::current($manager));
        $this->assertSame(ListViewMode::LEGACY, ListViewMode::current($other));

        $raw = app(SettingService::class)->get('ui.display_mode.u'.$manager->id);
        $this->assertSame(ListViewMode::CARDS, $raw);
        $this->assertNull(app(SettingService::class)->get('ui.display_mode.u'.$other->id));
    }

    public function test_guest_cannot_set_view_mode(): void
    {
        $this->post(route('ui.list-view'), [
            'mode' => ListViewMode::CARDS,
        ])->assertRedirect(route('login'));
    }

    public function test_invalid_mode_is_rejected(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('ui.list-view'), ['mode' => 'neon'])
            ->assertStatus(422);
    }
}
