<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\StoreGhCategoryRequest;
use App\Http\Requests\GeneralHouse\UpdateGhCategoryRequest;
use App\Models\GeneralHouse\GhServiceCategory;
use App\Services\GeneralHouse\Catalog\GhCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private readonly GhCategoryService $categories) {}

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
}
