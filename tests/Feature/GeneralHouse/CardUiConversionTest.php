<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhServiceCategory;
use App\Models\GeneralHouse\GhTreatment;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\Ui\ListViewMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardUiConversionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> route => data-gh-card-ui key */
    private function convertedIndexRoutes(): array
    {
        return [
            'gh.customers.index' => 'customers',
            'gh.zones.index' => 'zones',
            'gh.categories.index' => 'categories',
            'gh.products.index' => 'products',
            'gh.treatments.index' => 'treatments',
            'gh.offerings.index' => 'offerings',
            'gh.contracts.index' => 'contracts',
            'gh.invoices.index' => 'invoices',
            'gh.payments.index' => 'payments',
            'gh.credit-notes.index' => 'credit-notes',
            'gh.refunds.index' => 'refunds',
            'gh.financial-corrections.index' => 'financial-corrections',
        ];
    }

    private function enableCards(User $user): void
    {
        ListViewMode::put($user, ListViewMode::CARDS);
    }

    public function test_customers_index_defaults_to_legacy_table(): void
    {
        $manager = User::factory()->manager()->create();
        GhCustomer::factory()->create([
            'type' => GhCustomerType::B2C,
            'name_en' => 'Legacy Customer',
            'status' => GhCatalogStatus::ACTIVE,
        ]);

        $this->actingAs($manager)
            ->get(route('gh.customers.index'))
            ->assertOk()
            ->assertSee('table-hr', false)
            ->assertSee('data-list-view-mode="legacy"', false)
            ->assertDontSee('data-gh-card-ui="customers"', false);
    }

    public function test_customers_index_renders_card_grid_not_table(): void
    {
        $manager = User::factory()->manager()->create();
        $this->enableCards($manager);
        GhCustomer::factory()->create([
            'type' => GhCustomerType::B2C,
            'name_ar' => 'عميل بطاقة',
            'name_en' => 'Card Customer',
            'phone' => '0501112233',
            'status' => GhCatalogStatus::ACTIVE,
        ]);

        $this->actingAs($manager)
            ->get(route('gh.customers.index'))
            ->assertOk()
            ->assertSee('data-gh-card-ui="customers"', false)
            ->assertSee('gh-card-ui__grid', false)
            ->assertSee('gh-record-card', false)
            ->assertSee('عميل بطاقة', false)
            ->assertSee('0501112233', false)
            ->assertDontSee('table-hr', false);
    }

    public function test_zones_index_renders_card_grid_and_keeps_create_form(): void
    {
        $manager = User::factory()->manager()->create();
        $this->enableCards($manager);
        GhZone::factory()->create([
            'name_ar' => 'منطقة',
            'name_en' => 'Zone Card',
            'status' => GhCatalogStatus::ACTIVE,
        ]);

        $this->actingAs($manager)
            ->get(route('gh.zones.index'))
            ->assertOk()
            ->assertSee('data-gh-card-ui="zones"', false)
            ->assertSee('gh-record-card', false)
            ->assertSee('name="name_ar"', false)
            ->assertSee('Zone Card', false)
            ->assertDontSee('table-hr', false);
    }

    public function test_all_converted_management_indexes_use_card_shell(): void
    {
        $manager = User::factory()->manager()->create();
        GhServiceCategory::factory()->create();
        GhProduct::factory()->create();
        GhTreatment::factory()->create();
        GhProductOffering::factory()->create();
        GhCustomer::factory()->create();
        GhZone::factory()->create();

        $this->enableCards($manager);

        foreach ($this->convertedIndexRoutes() as $routeName => $uiKey) {
            $this->actingAs($manager)
                ->get(route($routeName))
                ->assertOk()
                ->assertSee('data-gh-card-ui="'.$uiKey.'"', false)
                ->assertDontSee('table-hr', false);
        }
    }

    public function test_dashboard_recent_lists_use_card_shell(): void
    {
        $manager = User::factory()->manager()->create();
        $this->enableCards($manager);

        $this->actingAs($manager)
            ->get(route('gh.dashboard'))
            ->assertOk()
            ->assertSee('data-gh-card-ui="dashboard"', false)
            ->assertDontSee('table-responsive', false);
    }

    public function test_invoices_index_uses_card_shell(): void
    {
        $manager = User::factory()->manager()->create();
        $this->enableCards($manager);

        $this->actingAs($manager)
            ->get(route('gh.invoices.index'))
            ->assertOk()
            ->assertSee('data-gh-card-ui="invoices"', false)
            ->assertDontSee('table-hr', false);
    }

    public function test_products_index_exposes_image_slot_on_cards(): void
    {
        $manager = User::factory()->manager()->create();
        $this->enableCards($manager);
        $product = GhProduct::factory()->create([
            'name_en' => 'Imaged Product',
            'name_ar' => 'منتج بصورة',
        ]);

        $html = $this->actingAs($manager)
            ->get(route('gh.products.index'))
            ->assertOk()
            ->assertSee('data-gh-card-ui="products"', false)
            ->assertSee('gh-record-card__thumb', false)
            ->assertSee('Imaged Product', false)
            ->getContent();

        $this->assertTrue(
            str_contains($html, 'gh-record-card__thumb--square')
            || str_contains($html, route('gh.products.image', $product)),
            'Product cards must expose a square thumb or authorized image URL.'
        );
    }

    public function test_offerings_create_form_aligned_grid_is_in_card_mode(): void
    {
        $manager = User::factory()->manager()->create();
        $this->enableCards($manager);

        $this->actingAs($manager)
            ->get(route('gh.offerings.index'))
            ->assertOk()
            ->assertSee('gh-offerings-create', false)
            ->assertSee('gh-offerings-create__hint', false);
    }
}
