<?php

namespace App\Http\Controllers\Gross;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gross\ImportGrossShippingCompanyRequest;
use App\Http\Requests\Gross\StoreGrossShippingCompanyRequest;
use App\Http\Requests\Gross\UpdateGrossShippingCompanyRequest;
use App\Models\Gross\GrossShippingCompany;
use App\Services\Gross\PieceLocationService;
use App\Services\Gross\ShippingCollectionStatementService;
use App\Services\Gross\ShippingCompanyService;
use App\Services\Gross\ShippingReturnsReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShippingCompanyController extends Controller
{
    public function __construct(
        private readonly ShippingCompanyService $companies,
        private readonly ShippingReturnsReportService $returnsReport,
        private readonly ShippingCollectionStatementService $statement,
        private readonly PieceLocationService $locations,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GrossShippingCompany::class);

        $q = trim((string) $request->query('q', ''));

        return view('gross.shipping.index', [
            'companies' => $this->companies->paginate(['q' => $q]),
            'filters' => ['q' => $q],
            'nameSuggestions' => $this->companies->nameSuggestions(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', GrossShippingCompany::class);

        return view('gross.shipping.create', [
            'nameSuggestions' => $this->companies->nameSuggestions(),
        ]);
    }

    public function store(StoreGrossShippingCompanyRequest $request): RedirectResponse
    {
        $company = $this->companies->create($request->validated());

        return redirect()
            ->route('gross.shipping.show', $company)
            ->with('success', __('gross.flash.shipping_created'));
    }

    public function show(GrossShippingCompany $shipping): View
    {
        $this->authorize('view', $shipping);

        return view('gross.shipping.show', [
            'company' => $shipping,
            'stats' => $this->companies->stats($shipping),
            'invoices' => $this->companies->paginateInvoices($shipping),
            'withCourierActive' => $this->locations->activeWithCourierForCompany($shipping),
            'returnsPreview' => $this->returnsReport->paginate($shipping, [], 50),
        ]);
    }

    public function returnsReport(Request $request, GrossShippingCompany $shipping): View
    {
        $this->authorize('view', $shipping);

        $filters = [
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
        ];

        return view('gross.shipping.returns', [
            'company' => $shipping,
            'filters' => $filters,
            'totals' => $this->returnsReport->totals($shipping, $filters),
            'entries' => $this->returnsReport->paginate($shipping, $filters),
        ]);
    }

    public function statement(Request $request, GrossShippingCompany $shipping): View
    {
        $this->authorize('view', $shipping);

        $filters = [
            'currency' => strtoupper(trim((string) $request->query('currency', 'EGP'))),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
        ];

        return view('gross.shipping.statement', [
            'company' => $shipping,
            'filters' => $filters,
            'statement' => $this->statement->build($shipping, $filters),
        ]);
    }

    public function edit(GrossShippingCompany $shipping): View
    {
        $this->authorize('update', $shipping);

        return view('gross.shipping.edit', [
            'company' => $shipping,
            'nameSuggestions' => $this->companies->nameSuggestions(),
        ]);
    }

    public function update(UpdateGrossShippingCompanyRequest $request, GrossShippingCompany $shipping): RedirectResponse
    {
        $this->companies->update($shipping, $request->validated());

        return redirect()
            ->route('gross.shipping.index', array_filter(['q' => $request->query('q')]))
            ->with('success', __('gross.flash.shipping_updated'));
    }

    public function destroy(GrossShippingCompany $shipping): RedirectResponse
    {
        $this->authorize('delete', $shipping);
        $this->companies->delete($shipping);

        return redirect()
            ->route('gross.shipping.index')
            ->with('success', __('gross.flash.shipping_deleted'));
    }

    public function template(): BinaryFileResponse|StreamedResponse
    {
        $this->authorize('create', GrossShippingCompany::class);

        $path = storage_path('app/tmp/gross-shipping-template.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $this->companies->writeTemplate($path);

        return response()->download(
            $path,
            'gross-shipping-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function import(ImportGrossShippingCompanyRequest $request): RedirectResponse
    {
        $path = $request->file('file')->getRealPath();
        $result = $this->companies->importFromPath((string) $path);

        $message = __('gross.flash.shipping_imported', [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ]);

        $redirect = redirect()->route('gross.shipping.index');

        if ($result['errors'] !== []) {
            return $redirect
                ->with('warning', $message)
                ->withErrors(['import' => $result['errors']]);
        }

        return $redirect->with('success', $message);
    }
}
