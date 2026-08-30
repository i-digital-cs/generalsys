<?php

namespace App\Http\Controllers\Gross;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gross\ImportGrossCustomerRequest;
use App\Http\Requests\Gross\StoreGrossCustomerRequest;
use App\Http\Requests\Gross\UpdateGrossCustomerRequest;
use App\Models\Gross\GrossCustomer;
use App\Services\Gross\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GrossCustomer::class);

        $q = trim((string) $request->query('q', ''));

        return view('gross.customers.index', [
            'customers' => $this->customers->paginate(['q' => $q]),
            'filters' => ['q' => $q],
            'nameSuggestions' => $this->customers->nameSuggestions(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', GrossCustomer::class);

        return view('gross.customers.create', [
            'nameSuggestions' => $this->customers->nameSuggestions(),
        ]);
    }

    public function store(StoreGrossCustomerRequest $request): RedirectResponse
    {
        $customer = $this->customers->create($request->validated());

        return redirect()
            ->route('gross.customers.show', $customer)
            ->with('success', __('gross.flash.customer_created'));
    }

    public function show(GrossCustomer $customer): View
    {
        $this->authorize('view', $customer);

        return view('gross.customers.show', compact('customer'));
    }

    public function edit(GrossCustomer $customer): View
    {
        $this->authorize('update', $customer);

        return view('gross.customers.edit', [
            'customer' => $customer,
            'nameSuggestions' => $this->customers->nameSuggestions(),
        ]);
    }

    public function update(UpdateGrossCustomerRequest $request, GrossCustomer $customer): RedirectResponse
    {
        $this->customers->update($customer, $request->validated());

        return redirect()
            ->route('gross.customers.index', array_filter(['q' => $request->query('q')]))
            ->with('success', __('gross.flash.customer_updated'));
    }

    public function destroy(GrossCustomer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);
        $this->customers->delete($customer);

        return redirect()
            ->route('gross.customers.index')
            ->with('success', __('gross.flash.customer_deleted'));
    }

    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GrossCustomer::class);

        $results = $this->customers->search((string) $request->query('q', ''));

        return response()->json(
            $results->map(fn (GrossCustomer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'address' => $c->address,
            ])->values()
        );
    }

    public function template(): BinaryFileResponse|StreamedResponse
    {
        $this->authorize('create', GrossCustomer::class);

        $path = storage_path('app/tmp/gross-customers-template.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $this->customers->writeTemplate($path);

        return response()->download(
            $path,
            'gross-customers-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function import(ImportGrossCustomerRequest $request): RedirectResponse
    {
        $path = $request->file('file')->getRealPath();
        $result = $this->customers->importFromPath((string) $path);

        $message = __('gross.flash.customer_imported', [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ]);

        $redirect = redirect()->route('gross.customers.index');

        if ($result['errors'] !== []) {
            return $redirect
                ->with('warning', $message)
                ->withErrors(['import' => $result['errors']]);
        }

        return $redirect->with('success', $message);
    }
}
