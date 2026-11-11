<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\StoreGhOrderRequest;
use App\Http\Requests\GeneralHouse\UpdateGhOrderRequest;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhZone;
use App\Services\GeneralHouse\Catalog\GhPricingResolver;
use App\Services\GeneralHouse\Orders\GhDriverEligibilityService;
use App\Services\GeneralHouse\Orders\GhDriverWorkspaceService;
use App\Services\GeneralHouse\Orders\GhOrderBoardService;
use App\Services\GeneralHouse\Orders\GhOrderService;
use App\Services\GeneralHouse\Orders\GhPickupEligibilityService;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhCustomerType;
use App\Support\GeneralHouse\GhOrderAccess;
use App\Support\GeneralHouse\GhOrderBoardTab;
use App\Support\GeneralHouse\GhOrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly GhOrderService $orders,
        private readonly GhDriverEligibilityService $drivers,
        private readonly GhPricingResolver $pricing,
        private readonly GhPickupEligibilityService $eligibility,
        private readonly GhDriverWorkspaceService $workspace,
        private readonly GhOrderBoardService $board,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GhOrder::class);

        $filters = [
            'tab' => GhOrderBoardTab::normalize((string) $request->query('tab', GhOrderBoardTab::PICKUP)),
            'q' => trim((string) $request->query('q', '')),
            'reference' => trim((string) $request->query('reference', '')),
            'customer' => trim((string) $request->query('customer', '')),
            'zone_id' => $request->query('zone_id', ''),
            'pickup_driver_id' => $request->query('pickup_driver_id', ''),
            'page' => max(1, (int) $request->query('page', 1)),
        ];

        return view('gh.orders.index', [
            'board' => $this->board->boardForAdmin($filters),
            'filters' => $filters,
            'zones' => GhZone::query()->orderBy('sort_order')->orderBy('name_ar')->get(),
            'drivers' => $this->drivers->eligibleDrivers(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', GhOrder::class);

        $customerId = (int) $request->query('customer_id', 0);
        $customer = $customerId > 0
            ? GhCustomer::query()->with('addresses.zone')->find($customerId)
            : null;

        $offerings = collect();
        if ($customer !== null && $customer->status === GhCatalogStatus::ACTIVE) {
            $offerings = $this->pricing->availableOfferingsForCustomer($customer);
        } else {
            // New B2C path: show standard sellable catalog before customer exists.
            $offerings = $this->pricing->availableOfferingsForCustomer(new GhCustomer([
                'type' => GhCustomerType::B2C,
                'status' => GhCatalogStatus::ACTIVE,
            ]));
        }

        return view('gh.orders.form', [
            'order' => null,
            'customer' => $customer,
            'customers' => GhCustomer::query()
                ->where('status', GhCatalogStatus::ACTIVE)
                ->orderBy('name_ar')
                ->limit(200)
                ->get(),
            'zones' => GhZone::query()
                ->where('status', GhCatalogStatus::ACTIVE)
                ->orderBy('sort_order')
                ->orderBy('name_ar')
                ->get(),
            'drivers' => $this->drivers->eligibleDrivers(),
            'offerings' => $offerings,
            'fullEdit' => true,
            'customerTypes' => GhCustomerType::ALL,
        ]);
    }

    public function store(StoreGhOrderRequest $request): RedirectResponse
    {
        $order = $this->orders->create($request->validated(), $request->user());

        return redirect()
            ->route('gh.orders.show', $order)
            ->with('success', __('gh.flash.order_created'));
    }

    public function show(GhOrder $order): View
    {
        $this->authorize('view', $order);
        $order->load([
            'customer',
            'address.zone',
            'zone',
            'contract',
            'lines',
            'receivedLines',
            'invoice',
            'attempts.actor',
            'pickupDriver',
            'deliveryDriver',
            'pickupConfirmedByUser',
            'deliveredByUser',
            'assignments.driver',
            'assignments.assignedByUser',
            'events.actor',
        ]);

        $user = request()->user();

        return view('gh.orders.show', [
            'order' => $order,
            'drivers' => $this->drivers->eligibleDrivers(),
            'eligibility' => $this->eligibility,
            'offerings' => collect(),
            'productGroups' => [],
            'canConfirmPickup' => GhOrderAccess::mayConfirmPickup($user, $order),
            'canConfirmDelivery' => GhOrderAccess::mayConfirmDelivery($user, $order)
                && $order->status === GhOrderStatus::DELIVERY,
            'canManageLogistics' => GhOrderAccess::mayManage($user),
            'displayTz' => $this->eligibility->displayTimezone($order->timezone),
        ]);
    }

    public function history(GhOrder $order): View
    {
        $this->authorize('view', $order);
        $order->load(['events.actor', 'customer']);

        return view('gh.orders.history', ['order' => $order]);
    }

    public function edit(GhOrder $order): View
    {
        $this->authorize('update', $order);
        $order->load(['customer.addresses.zone', 'lines', 'address', 'pickupDriver', 'deliveryDriver']);

        $offerings = $this->pricing->availableOfferingsForCustomer($order->customer);

        return view('gh.orders.form', [
            'order' => $order,
            'customer' => $order->customer,
            'customers' => collect([$order->customer]),
            'zones' => GhZone::query()
                ->where('status', GhCatalogStatus::ACTIVE)
                ->orderBy('sort_order')
                ->orderBy('name_ar')
                ->get(),
            'drivers' => $this->drivers->eligibleDrivers(),
            'offerings' => $offerings,
            'fullEdit' => $order->isEditableOperationally(),
            'customerTypes' => GhCustomerType::ALL,
        ]);
    }

    public function update(UpdateGhOrderRequest $request, GhOrder $order): RedirectResponse
    {
        $this->authorize('update', $order);

        if ($order->isEditableOperationally()) {
            $this->orders->update($order, $request->validated(), $request->user());
        } else {
            $this->orders->updateRestricted($order, $request->validated(), $request->user());
        }

        return redirect()
            ->route('gh.orders.show', $order)
            ->with('success', __('gh.flash.order_updated'));
    }
}
