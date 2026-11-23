<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\StoreGhCategoryRequest;
use App\Http\Requests\GeneralHouse\SyncGhCategoryTreatmentsRequest;
use App\Http\Requests\GeneralHouse\UpdateGhCategoryRequest;
use App\Models\GeneralHouse\GhServiceCategory;
use App\Services\GeneralHouse\Catalog\GhCategoryService;
use App\Services\GeneralHouse\Catalog\GhCategoryTreatmentService;
use App\Services\GeneralHouse\Catalog\GhTreatmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(
        private readonly GhCategoryService $categories,
        private readonly GhCategoryTreatmentService $categoryTreatments,
        private readonly GhTreatmentService $treatments,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GhServiceCategory::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
        ];

        return view('gh.categories.index', [
            'rows' => $this->categories->paginate($filters),
            'filters' => $filters,
        ]);
    }

    public function store(StoreGhCategoryRequest $request): RedirectResponse
    {
        $this->categories->create($request->validated());

        return redirect()
            ->route('gh.categories.index')
            ->with('success', __('gh.flash.category_created'));
    }

    public function update(UpdateGhCategoryRequest $request, GhServiceCategory $category): RedirectResponse
    {
        $this->authorize('update', $category);
        $this->categories->update($category, $request->validated());

        return redirect()
            ->route('gh.categories.index', array_filter([
                'q' => $request->query('q'),
                'status' => $request->query('status'),
            ]))
            ->with('success', __('gh.flash.category_updated'));
    }

    public function destroy(GhServiceCategory $category): RedirectResponse
    {
        $this->authorize('delete', $category);
        $this->categories->delete($category);

        return redirect()
            ->route('gh.categories.index')
            ->with('success', __('gh.flash.category_deleted'));
    }

    public function treatments(GhServiceCategory $category): View
    {
        $this->authorize('update', $category);
        $category->load('treatments');

        return view('gh.categories.treatments', [
            'category' => $category,
            'allTreatments' => $this->treatments->activeOrdered()
                ->concat($category->treatments)
                ->unique('id')
                ->sortBy([['sort_order', 'asc'], ['name_en', 'asc']])
                ->values(),
            'selectedIds' => $category->treatments->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function syncTreatments(
        SyncGhCategoryTreatmentsRequest $request,
        GhServiceCategory $category
    ): RedirectResponse {
        $this->authorize('update', $category);
        $this->categoryTreatments->sync(
            $category,
            array_values($request->validated('treatment_ids') ?? [])
        );

        return redirect()
            ->route('gh.categories.treatments', $category)
            ->with('success', __('gh.flash.category_treatments_updated'));
    }
}
