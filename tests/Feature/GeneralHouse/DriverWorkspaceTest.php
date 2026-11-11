<?php

namespace Tests\Feature\GeneralHouse;

use App\Models\GeneralHouse\GhB2bContract;
use App\Models\GeneralHouse\GhB2bContractOffering;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhCustomerAddress;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhOrderLine;
use App\Models\GeneralHouse\GhOrderReceivedLine;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhZone;
use App\Models\User;
use App\Services\GeneralHouse\Catalog\GhPricingResolver;
use App\Services\GeneralHouse\Invoices\GhInvoiceIssuanceService;
use App\Services\GeneralHouse\Invoices\GhInvoiceService;
use App\Services\GeneralHouse\Orders\GhDriverWorkspaceService;
use App\Support\GeneralHouse\GhAttemptReason;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhContractStatus;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhDriverMovementType;
use App\Support\GeneralHouse\GhMinOrderPolicy;
use App\Support\GeneralHouse\GhOrderStatus;
use App\Support\GeneralHouse\GhReceivedDiscrepancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DriverWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['orders', 'invoices', 'payments'] as $seq) {
            if (! DB::table('gh_sequences')->where('name', $seq)->exists()) {
                DB::table('gh_sequences')->insert([
                    'name' => $seq,
                    'next_value' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        config([
            'general_house.min_order_surcharge_tax_class' => null,
            'general_house.payments.driver_delivery_payment_enabled' => true,
            'general_house.standard_vat_rate' => '5.00',
        ]);
    }

    public function test_driver_dashboard_shows_only_assigned_tasks(): void
    {
        $pickup = User::factory()->driver()->create();
        $other = User::factory()->driver()->create();
        $mine = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'pickup_driver_id' => $pickup->id]);
        $theirs = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'pickup_driver_id' => $other->id]);

        $this->actingAs($pickup)->get(route('gh.driver.dashboard'))
            ->assertOk()
            ->assertSee($mine->reference, false)
            ->assertDontSee($theirs->reference, false);
    }

    public function test_cross_driver_task_access_denied(): void
    {
        $pickup = User::factory()->driver()->create();
        $delivery = User::factory()->driver()->create();
        $stranger = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => $delivery->id,
        ]);

        $this->actingAs($stranger)->get(route('gh.driver.pickup', $order))->assertForbidden();
        $this->actingAs($delivery)->get(route('gh.driver.pickup', $order))->assertForbidden();
        $this->actingAs($pickup)->get(route('gh.driver.delivery', $order))->assertForbidden();
        $this->actingAs($pickup)->get(route('gh.driver.pickup', $order))->assertOk();
    }

    public function test_pickup_confirmation_and_idempotent_retry(): void
    {
        $pickup = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $pickup->id,
        ]);
        $line = $this->attachLine($order, 5);

        $payload = ['notes' => 'Bag OK'];

        $this->actingAs($pickup)
            ->post(route('gh.orders.confirm-pickup', $order), $payload)
            ->assertRedirect(route('gh.driver.pickup', $order));

        $order->refresh();
        $this->assertSame(GhOrderStatus::WORKING, $order->status);
        $this->assertSame(5, (int) $line->fresh()->quantity);
        $this->assertSame(0, GhOrderReceivedLine::query()->where('order_id', $order->id)->count());
        $this->assertNotNull($order->picked_up_at);
        $this->assertSame((int) $pickup->id, (int) $order->pickup_confirmed_by);

        $this->actingAs($pickup)
            ->post(route('gh.orders.confirm-pickup', $order), $payload)
            ->assertRedirect();
        $this->assertSame(GhOrderStatus::WORKING, $order->fresh()->status);
        $this->assertSame(0, GhOrderReceivedLine::query()->where('order_id', $order->id)->count());
    }

    public function test_driver_pickup_page_has_no_mandatory_item_controls(): void
    {
        $pickup = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $pickup->id,
        ]);

        $html = $this->actingAs($pickup)
            ->withSession(['locale' => 'en'])
            ->get(route('gh.driver.pickup', $order))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('gh.driver.confirm_pickup_clothes'), $html);
        $this->assertStringContainsString(__('gh.driver.pickup_time'), $html);
        $this->assertStringNotContainsString('name="received[', $html);
        $this->assertStringNotContainsString('gh-pickup-picker', $html);
    }

    public function test_delivery_confirmation_unpaid_and_not_from_working(): void
    {
        $manager = User::factory()->manager()->create();
        $pickup = User::factory()->driver()->create();
        $delivery = User::factory()->driver()->create();

        $invoice = $this->issueWorkingToDelivery($manager, $pickup, $delivery);
        $order = $invoice->order;

        $this->assertSame(GhOrderStatus::DELIVERY, $order->fresh()->status);
        $this->assertSame('UNPAID', $invoice->fresh()->payment_status);

        $this->actingAs($delivery)->get(route('gh.driver.delivery', $order))
            ->assertOk()
            ->assertSee(__('gh.driver.confirm_delivery_and_pay'), false)
            ->assertSee(__('gh.driver.payment_method'), false)
            ->assertDontSee(__('gh.payments.confirm_cash'), false);

        $this->actingAs($delivery)
            ->post(route('gh.orders.confirm-delivery', $order), [
                'notes' => 'Left at door',
                'payment_method' => 'CASH',
            ])
            ->assertRedirect(route('gh.driver.delivery', $order));
        $this->assertSame(GhOrderStatus::COMPLETED, $order->fresh()->status);
        $this->assertSame('PAID', $invoice->fresh()->payment_status);

        // Cannot confirm delivery while WORKING (no invoice path for driver).
        $working = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => now(),
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => $delivery->id,
        ]);
        $this->actingAs($delivery)
            ->post(route('gh.orders.confirm-delivery', $working), ['notes' => 'too early'])
            ->assertSessionHasErrors();
        $this->assertSame(GhOrderStatus::WORKING, $working->fresh()->status);
    }

    public function test_failed_attempts_with_reason_and_payment_invoice_denied(): void
    {
        $pickup = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $pickup->id,
        ]);

        $this->actingAs($pickup)->post(route('gh.orders.failed-pickup', $order), [
            'reason_code' => GhAttemptReason::CUSTOMER_UNAVAILABLE,
            'notes' => 'No answer',
        ])->assertRedirect(route('gh.driver.pickup', $order));

        $this->assertDatabaseHas('gh_order_attempts', [
            'order_id' => $order->id,
            'attempt_type' => 'PICKUP',
            'outcome' => 'FAILED',
        ]);
        $this->assertSame(GhOrderStatus::PICKUP, $order->fresh()->status);

        $this->actingAs($pickup)->get(route('gh.invoices.index'))->assertForbidden();
        $this->actingAs($pickup)->get(route('gh.payments.index'))->assertForbidden();
    }

    public function test_manager_cannot_use_driver_workspace_routes(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->get(route('gh.driver.dashboard'))->assertForbidden();
    }

    public function test_arabic_dashboard_renders(): void
    {
        $driver = User::factory()->driver()->create();
        $this->withSession(['locale' => 'ar'])
            ->actingAs($driver)
            ->get(route('gh.driver.dashboard'))
            ->assertOk()
            ->assertSee(__('gh.driver.dashboard', [], 'ar'), false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function windowIn(int $hours): array
    {
        $start = Carbon::now()->addHours($hours);

        return ['pickup_starts_at' => $start, 'pickup_ends_at' => $start->copy()->addHours(2)];
    }

    public function test_dashboard_separates_actionable_overdue_and_informational_pickups(): void
    {
        $driver = User::factory()->driver()->create();
        $far = $this->makeOrder(['status' => GhOrderStatus::LATER, 'pickup_driver_id' => $driver->id] + $this->windowIn(48));
        $near = $this->makeOrder(['status' => GhOrderStatus::LATER, 'pickup_driver_id' => $driver->id] + $this->windowIn(23));
        $overdue = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'pickup_driver_id' => $driver->id] + $this->windowIn(-72));

        $data = app(GhDriverWorkspaceService::class)->dashboard($driver);

        $actionable = $data['actionable_pickups']->pluck('id')->all();
        $this->assertContains($near->id, $actionable);
        $this->assertContains($overdue->id, $actionable);
        $this->assertNotContains($far->id, $actionable);
        $this->assertSame([$far->id], $data['upcoming_pickups']->pluck('id')->all());

        $this->actingAs($driver)->get(route('gh.driver.dashboard', ['tab' => 'pickup']))
            ->assertOk()
            ->assertSee('data-order-id="'.$near->id.'"', false)
            ->assertDontSee('data-order-id="'.$far->id.'"', false);

        $this->actingAs($driver)->get(route('gh.driver.dashboard', ['tab' => 'later']))
            ->assertOk()
            ->assertSee('data-order-id="'.$far->id.'"', false)
            ->assertSee($far->reference, false);
    }

    public function test_driver_reorder_persists_and_dashboard_honours_positions(): void
    {
        $driver = User::factory()->driver()->create();
        $first = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'pickup_driver_id' => $driver->id] + $this->windowIn(1));
        $second = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'pickup_driver_id' => $driver->id] + $this->windowIn(2));
        $service = app(GhDriverWorkspaceService::class);

        $this->assertSame([$first->id, $second->id], $service->dashboard($driver)['actionable_pickups']->pluck('id')->all());

        $this->actingAs($driver)
            ->postJson(route('gh.driver.reorder-pickups'), ['order_ids' => [$second->id, $first->id]])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame([$second->id, $first->id], $service->dashboard($driver)['actionable_pickups']->pluck('id')->all());
        $this->assertDatabaseHas('gh_driver_list_positions', ['driver_user_id' => $driver->id, 'list_type' => 'PICKUP', 'order_id' => $second->id, 'position' => 0]);
        $this->assertDatabaseHas('gh_driver_list_positions', ['driver_user_id' => $driver->id, 'list_type' => 'PICKUP', 'order_id' => $first->id, 'position' => 1]);
    }

    public function test_driver_cannot_reorder_other_drivers_or_non_actionable_orders(): void
    {
        $driver = User::factory()->driver()->create();
        $other = User::factory()->driver()->create();
        $mine = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'pickup_driver_id' => $driver->id] + $this->windowIn(1));
        $theirs = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'pickup_driver_id' => $other->id] + $this->windowIn(1));
        $notYet = $this->makeOrder(['status' => GhOrderStatus::LATER, 'pickup_driver_id' => $driver->id] + $this->windowIn(48));

        $this->actingAs($driver)
            ->postJson(route('gh.driver.reorder-pickups'), ['order_ids' => [$mine->id, $theirs->id]])
            ->assertForbidden();
        $this->actingAs($driver)
            ->postJson(route('gh.driver.reorder-deliveries'), ['order_ids' => [$mine->id]])
            ->assertForbidden();
        $this->actingAs($driver)
            ->postJson(route('gh.driver.reorder-pickups'), ['order_ids' => [$notYet->id]])
            ->assertUnprocessable();
        $this->actingAs(User::factory()->manager()->create())
            ->postJson(route('gh.driver.reorder-pickups'), ['order_ids' => [$mine->id]])
            ->assertForbidden();

        $this->assertDatabaseCount('gh_driver_list_positions', 0);
    }

    public function test_pickup_page_blocks_later_order_outside_window_with_clear_notice(): void
    {
        $driver = User::factory()->driver()->create();
        $order = $this->makeOrder(['status' => GhOrderStatus::LATER, 'pickup_driver_id' => $driver->id] + $this->windowIn(48));

        $this->actingAs($driver)->get(route('gh.driver.pickup', $order))
            ->assertOk()
            ->assertSee(e(trans('gh.orders.pickup_not_eligible', ['hours' => 24, 'time' => $order->pickup_starts_at->timezone($order->timezone)->translatedFormat('Y-m-d H:i')])), false)
            ->assertDontSee('gh-driver-pickup-form', false);

        $this->assertSame(GhOrderStatus::LATER, $order->fresh()->status);
    }

    public function test_opening_eligible_later_pickup_promotes_it_and_b2b_can_confirm_with_sellable_offering(): void
    {
        $driver = User::factory()->driver()->create();
        $customer = GhCustomer::factory()->b2b()->create();
        $address = GhCustomerAddress::factory()->create(['customer_id' => $customer->id, 'zone_id' => GhZone::factory()->create()->id]);
        $order = $this->makeOrder([
            'status' => GhOrderStatus::LATER,
            'pickup_driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'address_id' => $address->id,
        ] + $this->windowIn(23));
        $offering = GhProductOffering::factory()->create(['standard_price' => '12.00']);

        $this->actingAs($driver)->get(route('gh.driver.pickup', $order))
            ->assertOk()
            ->assertDontSee('gh-pickup-picker', false)
            ->assertSee('gh-driver-pickup-form', false);
        $this->assertSame(GhOrderStatus::PICKUP, $order->fresh()->status);
        $this->assertSame(0, $customer->contracts()->count());

        $this->actingAs($driver)
            ->post(route('gh.orders.confirm-pickup', $order), ['notes' => 'package only'])
            ->assertRedirect(route('gh.driver.pickup', $order));

        $this->assertSame(GhOrderStatus::WORKING, $order->fresh()->status);
        $this->assertSame(0, GhOrderReceivedLine::query()->where('order_id', $order->id)->count());

        // B2B without contract: builder opens empty with explanatory catalog mode (no silent standard prices).
        $manager = User::factory()->manager()->create();
        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order->fresh(['zone', 'customer']), $manager);
        $this->assertSame(0, $draft->lines()->count());
        $meta = app(GhPricingResolver::class)
            ->invoiceBuilderCatalog($customer);
        $this->assertSame('B2B_NO_CONTRACT', $meta['mode']);
        $this->assertSame([], $meta['groups']);
        $this->assertNotNull($offering->id);
    }

    public function test_mixed_b2c_b2b_tasks_share_driver_list_and_reorder(): void
    {
        $driver = User::factory()->driver()->create();
        $b2c = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'pickup_driver_id' => $driver->id] + $this->windowIn(1));
        $b2bCustomer = GhCustomer::factory()->b2b()->create(['name_en' => 'Hotel Mixed']);
        $b2bAddress = GhCustomerAddress::factory()->create([
            'customer_id' => $b2bCustomer->id,
            'zone_id' => GhZone::factory()->create()->id,
        ]);
        $b2b = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $driver->id,
            'customer_id' => $b2bCustomer->id,
            'address_id' => $b2bAddress->id,
            'zone_id' => $b2bAddress->zone_id,
        ] + $this->windowIn(2));

        $this->actingAs($driver)->get(route('gh.driver.dashboard', ['tab' => 'pickup']))
            ->assertOk()
            ->assertSee($b2c->reference, false)
            ->assertSee($b2b->reference, false)
            ->assertSee('gh-order-card', false)
            ->assertSee('data-gh-sortable', false)
            ->assertSee('data-sort-handle', false);

        $this->actingAs($driver)
            ->postJson(route('gh.driver.reorder-pickups'), [
                'order_ids' => [$b2b->id, $b2c->id],
                'previous_order_ids' => [$b2c->id, $b2b->id],
            ])
            ->assertOk();

        $ids = app(GhDriverWorkspaceService::class)->dashboard($driver)['actionable_pickups']->pluck('id')->all();
        $this->assertSame([$b2b->id, $b2c->id], $ids);
    }

    public function test_reorder_rejects_stale_previous_order_ids(): void
    {
        $driver = User::factory()->driver()->create();
        $a = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'pickup_driver_id' => $driver->id] + $this->windowIn(1));
        $b = $this->makeOrder(['status' => GhOrderStatus::PICKUP, 'pickup_driver_id' => $driver->id] + $this->windowIn(2));

        $this->actingAs($driver)
            ->postJson(route('gh.driver.reorder-pickups'), ['order_ids' => [$b->id, $a->id]])
            ->assertOk();

        $this->actingAs($driver)
            ->postJson(route('gh.driver.reorder-pickups'), [
                'order_ids' => [$a->id, $b->id],
                'previous_order_ids' => [$a->id, $b->id],
            ])
            ->assertUnprocessable();
    }

    public function test_b2b_out_of_contract_received_item_requires_staff_pricing_before_invoice(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $customer = GhCustomer::factory()->b2b()->create();
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => GhZone::factory()->create([
                'min_order_amount' => '0.00',
                'min_order_policy' => GhMinOrderPolicy::SURCHARGE,
            ])->id,
        ]);
        $inContract = GhProductOffering::factory()->create(['standard_price' => '8.00']);
        $outOfContract = GhProductOffering::factory()->create(['standard_price' => '99.00']);
        $contract = GhB2bContract::factory()->create([
            'customer_id' => $customer->id,
            'status' => GhContractStatus::ACTIVE,
            'effective_from' => now()->subDay()->toDateString(),
            'effective_to' => null,
        ]);
        GhB2bContractOffering::factory()->create([
            'contract_id' => $contract->id,
            'offering_id' => $inContract->id,
            'contract_price' => '5.00',
            'status' => GhCatalogStatus::ACTIVE,
        ]);

        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $address->zone_id,
            'contract_id' => $contract->id,
        ] + $this->windowIn(1));

        $this->actingAs($driver)->post(route('gh.orders.confirm-pickup', $order), [
            'notes' => 'package',
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame(GhOrderStatus::WORKING, $order->status);
        $this->assertSame(0, GhOrderReceivedLine::query()->where('order_id', $order->id)->count());

        // Direct invoice path: empty draft opens; OOC line needs explicit price approval before issue.
        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order->fresh(['zone', 'customer']), $manager);
        $this->assertSame(0, $draft->lines()->count());

        $this->actingAs($manager)->put(route('gh.invoices.update', $draft), [
            'draft_version' => $draft->draft_version,
            'invoice_discount_type' => 'NONE',
            'invoice_discount_value' => '0',
            'min_order_override_type' => 'APPLY_DEFAULT',
            'lines' => [
                ['offering_id' => $inContract->id, 'quantity' => 2],
                [
                    'offering_id' => $outOfContract->id,
                    'quantity' => 1,
                    'out_of_contract' => '1',
                    'approve_price' => '0',
                ],
            ],
        ])->assertRedirect();

        $draft->refresh();
        $ooc = $draft->lines()->where('offering_id', $outOfContract->id)->firstOrFail();
        $this->assertTrue((bool) $ooc->pricing_review_required);
        $this->assertSame('0.00', (string) $ooc->unit_price);
        $this->assertSame('5.00', (string) $draft->lines()->where('offering_id', $inContract->id)->value('unit_price'));

        $this->expectException(ValidationException::class);
        app(GhInvoiceIssuanceService::class)->issue($draft, $manager, (int) $draft->draft_version);
    }

    public function test_driver_can_load_product_image_for_pickup_picker(): void
    {
        $driver = User::factory()->driver()->create();
        $path = 'gh-products/test-thumb.jpg';
        Storage::disk('local')->put($path, 'fake-image');
        $offering = GhProductOffering::factory()->create(['standard_price' => '10.00']);
        $offering->product->update(['image_path' => $path]);
        $offering->load(['product', 'treatment']);

        $this->actingAs($driver)
            ->get(route('gh.products.image', $offering->product))
            ->assertOk();

        $this->actingAs(User::factory()->employee()->create())
            ->get(route('gh.products.image', $offering->product))
            ->assertForbidden();

        $groups = app(GhDriverWorkspaceService::class)->pickupProductGroups(
            collect([['offering' => $offering]])
        );
        $this->assertNotEmpty($groups[0]['image_url'] ?? null);
        $this->assertStringContainsString('/products/'.$offering->product_id.'/image', $groups[0]['image_url']);
    }

    public function test_delivery_without_delivery_driver_is_excluded_from_driver_lists(): void
    {
        $pickup = User::factory()->driver()->create();
        $order = $this->makeOrder([
            'status' => GhOrderStatus::DELIVERY,
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => null,
            'picked_up_at' => now()->subHour(),
            'invoice_issued_at' => now()->subMinutes(30),
        ]);

        $dash = app(GhDriverWorkspaceService::class)->dashboard($pickup);
        $this->assertFalse($dash['actionable_pickups']->contains('id', $order->id));
        $this->assertFalse($dash['upcoming_pickups']->contains('id', $order->id));
        $this->assertFalse($dash['pending_deliveries']->contains('id', $order->id));
        // Pickup movement may appear as completed; never a delivery movement without assignee.
        $this->assertFalse($dash['completed']->contains(
            fn ($m) => $m->order->id === $order->id && $m->type === GhDriverMovementType::DELIVERY
        ));

        $this->actingAs(User::factory()->manager()->create())
            ->get(route('gh.orders.show', $order))
            ->assertOk()
            ->assertSee(__('gh.orders.not_assigned'), false)
            ->assertSee(__('gh.orders.delivery_unassigned_hint'), false);
    }

    public function test_staff_can_approve_out_of_contract_pricing_then_invoice(): void
    {
        $manager = User::factory()->manager()->create();
        $driver = User::factory()->driver()->create();
        $customer = GhCustomer::factory()->b2b()->create();
        $zone = GhZone::factory()->create([
            'min_order_amount' => '0.00',
            'min_order_policy' => GhMinOrderPolicy::SURCHARGE,
        ]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
        ]);
        $offering = GhProductOffering::factory()->create(['standard_price' => '40.00']);
        $order = $this->makeOrder([
            'status' => GhOrderStatus::PICKUP,
            'pickup_driver_id' => $driver->id,
            'delivery_driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
        ] + $this->windowIn(1));

        $this->actingAs($driver)->post(route('gh.orders.confirm-pickup', $order), [
            'notes' => 'package',
        ])->assertRedirect();

        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order->fresh(['zone', 'customer']), $manager);
        $this->assertSame(0, $draft->lines()->count());

        $this->actingAs($manager)->put(route('gh.invoices.update', $draft), [
            'draft_version' => $draft->draft_version,
            'invoice_discount_type' => 'NONE',
            'invoice_discount_value' => '0',
            'min_order_override_type' => 'APPLY_DEFAULT',
            'lines' => [[
                'offering_id' => $offering->id,
                'quantity' => 1,
                'out_of_contract' => '1',
                'approve_price' => '1',
                'unit_price' => '17.50',
            ]],
        ])->assertRedirect();

        $invoiceLine = $draft->fresh()->lines()->firstOrFail();
        $this->assertFalse((bool) $invoiceLine->pricing_review_required);
        $this->assertTrue((bool) $invoiceLine->staff_price_approved);
        $this->assertSame('17.50', (string) $invoiceLine->unit_price);
        $this->assertNotSame('40.00', (string) $invoiceLine->unit_price);
    }

    private function makeOrder(array $overrides = []): GhOrder
    {
        $tz = config('general_house.timezone', 'Asia/Dubai');
        $zone = GhZone::factory()->create();
        $customer = GhCustomer::factory()->create(['type' => GhCustomerType::B2C]);
        $address = GhCustomerAddress::factory()->create([
            'customer_id' => $customer->id,
            'zone_id' => $zone->id,
            'latitude' => '25.2048000',
            'longitude' => '55.2708000',
        ]);
        $manager = User::factory()->manager()->create();

        return GhOrder::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'zone_id' => $zone->id,
            'timezone' => $tz,
            'pickup_starts_at' => Carbon::now($tz)->setTime(10, 0),
            'pickup_ends_at' => Carbon::now($tz)->setTime(12, 0),
            'created_by' => $manager->id,
        ], $overrides));
    }

    private function attachLine(GhOrder $order, int $qty = 1): GhOrderLine
    {
        $offering = GhProductOffering::factory()->create(['standard_price' => '10.00']);

        return GhOrderLine::factory()->create([
            'order_id' => $order->id,
            'offering_id' => $offering->id,
            'quantity' => $qty,
            'unit_price_estimate' => '10.00',
            'product_name_ar' => 'منتج',
            'product_name_en' => 'Product',
            'treatment_name_ar' => 'كي',
            'treatment_name_en' => 'Iron',
        ]);
    }

    private function issueWorkingToDelivery(User $manager, User $pickup, User $delivery): GhInvoice
    {
        $order = $this->makeOrder([
            'status' => GhOrderStatus::WORKING,
            'picked_up_at' => Carbon::now('UTC'),
            'pickup_driver_id' => $pickup->id,
            'delivery_driver_id' => $delivery->id,
        ]);
        $line = $this->attachLine($order, 2);
        GhOrderReceivedLine::query()->create([
            'order_id' => $order->id,
            'order_line_id' => $line->id,
            'offering_id' => $line->offering_id,
            'quantity' => 2,
            'discrepancy_type' => GhReceivedDiscrepancy::MATCH,
            'sort_order' => 0,
            'product_name_ar' => 'منتج',
            'product_name_en' => 'Product',
            'treatment_name_ar' => 'كي',
            'treatment_name_en' => 'Iron',
            'unit_price_estimate' => '10.00',
            'currency' => 'AED',
            'price_source' => 'STANDARD',
        ]);
        // Zone min 0 via factory default may surcharge — set zone min 0
        GhZone::query()->whereKey($order->zone_id)->update([
            'min_order_amount' => '0.00',
            'min_order_policy' => GhMinOrderPolicy::SURCHARGE,
        ]);

        $draft = app(GhInvoiceService::class)->createDraftFromOrder($order->fresh(['zone', 'customer']), $manager);

        return app(GhInvoiceIssuanceService::class)->issue($draft, $manager, (int) $draft->draft_version);
    }
}
