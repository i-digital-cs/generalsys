<?php

namespace App\Http\Controllers\Gross;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gross\ImportGrossSupplierRequest;
use App\Http\Requests\Gross\StoreGrossSupplierRequest;
use App\Http\Requests\Gross\UpdateGrossSupplierRequest;
use App\Models\Gross\GrossSupplier;
use App\Services\Gross\SupplierService;
use App\Services\Gross\SupplierStatementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $suppliers,
        private readonly SupplierStatementService $statements,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GrossSupplier::class);

        $q = trim((string) $request->query('q', ''));
        $suppliers = $this->suppliers->paginate(['q' => $q]);
        $balances = $this->statements->balancesForSuppliers(
            $suppliers->getCollection()->pluck('id'),
            'EGP'
        );

        return view('gross.suppliers.index', [
            'suppliers' => $suppliers,
            'balances' => $balances,
            'filters' => ['q' => $q],
            'nameSuggestions' => $this->suppliers->nameSuggestions(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', GrossSupplier::class);

        return view('gross.suppliers.create', [
            'nameSuggestions' => $this->suppliers->nameSuggestions(),
        ]);
    }

    public function store(StoreGrossSupplierRequest $request): RedirectResponse
    {
        $supplier = $this->suppliers->create($request->validated());

        return redirect()
            ->route('gross.suppliers.index')
            ->with('success', __('gross.flash.supplier_created'));
    }

    public function show(GrossSupplier $supplier): View
    {
        $this->authorize('view', $supplier);

        return view('gross.suppliers.show', compact('supplier'));
    }

    public function statement(Request $request, GrossSupplier $supplier): View
    {
        $this->authorize('view', $supplier);

        $filters = [
            'currency' => strtoupper(trim((string) $request->query('currency', 'EGP'))),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
        ];

        return view('gross.suppliers.statement', [
            'supplier' => $supplier,
            'filters' => $filters,
            'statement' => $this->statements->build($supplier, $filters),
        ]);
    }

    public function edit(GrossSupplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('gross.suppliers.edit', [
            'supplier' => $supplier,
            'nameSuggestions' => $this->suppliers->nameSuggestions(),
        ]);
    }

    public function update(UpdateGrossSupplierRequest $request, GrossSupplier $supplier): RedirectResponse
    {
        $this->suppliers->update($supplier, $request->validated());

        return redirect()
            ->route('gross.suppliers.index', array_filter(['q' => $request->query('q')]))
            ->with('success', __('gross.flash.supplier_updated'));
    }

    public function destroy(GrossSupplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);
        $this->suppliers->delete($supplier);

        return redirect()
            ->route('gross.suppliers.index')
            ->with('success', __('gross.flash.supplier_deleted'));
    }

    public function template(): BinaryFileResponse|StreamedResponse
    {
        $this->authorize('create', GrossSupplier::class);

        $path = storage_path('app/tmp/gross-suppliers-template.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $this->suppliers->writeTemplate($path);

        return response()->download(
            $path,
            'gross-suppliers-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function import(ImportGrossSupplierRequest $request): RedirectResponse
    {
        $path = $request->file('file')->getRealPath();
        $result = $this->suppliers->importFromPath((string) $path);

        $message = __('gross.flash.supplier_imported', [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ]);

        $redirect = redirect()->route('gross.suppliers.index');

        if ($result['errors'] !== []) {
            return $redirect
                ->with('warning', $message)
                ->withErrors(['import' => $result['errors']]);
        }

        return $redirect->with('success', $message);
    }
}
