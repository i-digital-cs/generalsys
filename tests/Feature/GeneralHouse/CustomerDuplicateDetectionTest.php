<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\User;
use App\Services\GeneralHouse\Customers\GhCustomerService;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhCustomerType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerDuplicateDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_exact_and_normalized_phone_match(): void
    {
        $manager = User::factory()->manager()->create();
        $existing = GhCustomer::factory()->create([
            'type' => GhCustomerType::B2C,
            'phone' => '0501112233',
            'name_ar' => 'عميل أصلي',
            'name_en' => 'Original',
        ]);

        $json = $this->actingAs($manager)
            ->getJson(route('gh.customers.lookup', ['phone' => '+971 50 111 2233']))
            ->assertOk()
            ->json();

        $this->assertCount(1, $json['phone_matches']);
        $this->assertSame($existing->id, $json['phone_matches'][0]['id']);
        $this->assertStringContainsString('customers/'.$existing->id, $json['phone_matches'][0]['url']);
    }

    public function test_lookup_name_match_is_warning_only(): void
    {
        $manager = User::factory()->manager()->create();
        GhCustomer::factory()->create([
            'phone' => '0509998877',
            'name_ar' => 'محمد أحمد',
            'name_en' => 'Mohamed Ahmed',
        ]);

        $json = $this->actingAs($manager)
            ->getJson(route('gh.customers.lookup', [
                'phone' => '0500001111',
                'name_ar' => 'محمد',
                'name_en' => 'Mohamed',
            ]))
            ->assertOk()
            ->json();

        $this->assertSame([], $json['phone_matches']);
        $this->assertNotEmpty($json['name_matches']);
    }

    public function test_store_rejects_duplicate_phone_without_confirmation(): void
    {
        $manager = User::factory()->manager()->create();
        GhCustomer::factory()->create(['phone' => '0502223344']);

        $this->actingAs($manager)->post(route('gh.customers.store'), [
            'type' => GhCustomerType::B2C,
            'name_ar' => 'جديد',
            'name_en' => 'New',
            'phone' => '+971502223344',
            'status' => GhCatalogStatus::ACTIVE,
        ])->assertSessionHasErrors('phone');

        $this->assertSame(1, GhCustomer::query()->where('phone', 'like', '%2223344%')->count());
    }

    public function test_store_allows_explicit_duplicate_phone(): void
    {
        $manager = User::factory()->manager()->create();
        GhCustomer::factory()->create(['phone' => '0503334455', 'name_ar' => 'أول']);

        $this->actingAs($manager)->post(route('gh.customers.store'), [
            'type' => GhCustomerType::B2C,
            'name_ar' => 'ثاني',
            'name_en' => 'Second',
            'phone' => '0503334455',
            'status' => GhCatalogStatus::ACTIVE,
            'allow_duplicate_phone' => '1',
        ])->assertRedirect();

        $this->assertSame(2, GhCustomer::query()->get()->filter(
            fn (GhCustomer $c) => str_contains((string) $c->phone, '3334455')
        )->count());
    }

    public function test_service_create_is_safe_under_sequential_race_style_retries(): void
    {
        $svc = app(GhCustomerService::class);
        $svc->create([
            'type' => GhCustomerType::B2C,
            'name_ar' => 'A',
            'phone' => '0504445566',
            'status' => GhCatalogStatus::ACTIVE,
        ]);

        try {
            $svc->create([
                'type' => GhCustomerType::B2C,
                'name_ar' => 'B',
                'phone' => '971504445566',
                'status' => GhCatalogStatus::ACTIVE,
            ]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('phone', $e->errors());
        }

        $this->assertSame(1, GhCustomer::query()->count());
    }

    public function test_create_form_exposes_ar_en_duplicate_labels(): void
    {
        $manager = User::factory()->manager()->create();

        $en = $this->actingAs($manager)->withSession(['locale' => 'en'])
            ->get(route('gh.customers.create'))->assertOk()->getContent();
        $this->assertStringContainsString('allow_duplicate_phone', $en);
        $this->assertStringContainsString(__('gh.customers.phone_match_title'), $en);

        $ar = $this->actingAs($manager)->withSession(['locale' => 'ar'])
            ->get(route('gh.customers.create'))->assertOk()->getContent();
        $this->assertStringContainsString('إنشاء عميل منفصل بنفس رقم الهاتف', $ar);
        $this->assertStringContainsString('allow_duplicate_phone', $ar);
        $this->assertMatchesRegularExpression('/phone_match_title|\\\\u0639|عملاء/', $ar);
    }
}
