<?php

namespace App\Http\Controllers\Gross;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gross\ImportGrossCategoryRequest;
use App\Http\Requests\Gross\StoreGrossCategoryRequest;
use App\Http\Requests\Gross\UpdateGrossCategoryRequest;
use App\Models\Gross\GrossCategory;
use App\Models\Gross\GrossItem;
use App\Services\Gross\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categories) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GrossItem::class);

        $q = trim((string) $request->query('q', ''));

        return view('gross.categories.index', [
            'rows' => $this->categories->searchable($q),
            'parents' => $this->categories->roots(),
            'filters' => ['q' => $q],
        ]);
    }

    public function store(StoreGrossCategoryRequest $request): RedirectResponse
    {
        $this->categories->create($request->validated());

        return redirect()
            ->route('gross.categories.index')
            ->with('success', __('gross.flash.category_created'));
    }

    public function update(UpdateGrossCategoryRequest $request, GrossCategory $category): RedirectResponse
    {
        $this->categories->update($category, $request->validated());

        return redirect()
            ->route('gross.categories.index', array_filter(['q' => $request->query('q')]))
            ->with('success', __('gross.flash.category_updated'));
    }

    public function destroy(GrossCategory $category): RedirectResponse
    {
        $this->authorize('create', GrossItem::class);
        $this->categories->delete($category);

        return redirect()
            ->route('gross.categories.index')
            ->with('success', __('gross.flash.category_deleted'));
    }

    public function template(): BinaryFileResponse|StreamedResponse
    {
        $this->authorize('create', GrossItem::class);

        $path = storage_path('app/tmp/gross-categories-template.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $this->categories->writeTemplate($path);

        return response()->download(
            $path,
            'gross-categories-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function import(ImportGrossCategoryRequest $request): RedirectResponse
    {
        $path = $request->file('file')->getRealPath();
        $result = $this->categories->importFromPath((string) $path);

        $message = __('gross.flash.category_imported', [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ]);

        $redirect = redirect()->route('gross.categories.index');

        if ($result['errors'] !== []) {
            return $redirect
                ->with('warning', $message)
                ->withErrors(['import' => $result['errors']]);
        }

        return $redirect->with('success', $message);
    }
}
