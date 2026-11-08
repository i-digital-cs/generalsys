<?php

namespace App\Http\Controllers\GeneralHouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\GeneralHouse\StoreGhProductRequest;
use App\Http\Requests\GeneralHouse\UpdateGhProductRequest;
use App\Models\GeneralHouse\GhProduct;
use App\Services\GeneralHouse\Catalog\GhCategoryService;
use App\Services\GeneralHouse\Catalog\GhProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function __construct(
        private readonly GhProductService $products,
        private readonly GhCategoryService $categories,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GhProduct::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'category_id' => $request->query('category_id', ''),
        ];

        return view('gh.products.index', [
            'rows' => $this->products->paginate($filters),
            'categories' => $this->categories->allOrdered(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', GhProduct::class);

        return view('gh.products.form', [
            'product' => null,
            'categories' => $this->categories->activeOrdered(),
        ]);
    }

    public function store(StoreGhProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $image = $request->file('image');
        unset($data['image']);
        $this->products->create($data, $image);

        return redirect()
            ->route('gh.products.index')
            ->with('success', __('gh.flash.product_created'));
    }

    public function edit(GhProduct $product): View
    {
        $this->authorize('update', $product);

        return view('gh.products.form', [
            'product' => $product->load('category'),
            'categories' => $this->categories->activeOrdered()
                ->push($product->category)
                ->filter()
                ->unique('id')
                ->values(),
        ]);
    }

    public function update(UpdateGhProductRequest $request, GhProduct $product): RedirectResponse
    {
        $this->authorize('update', $product);
        $data = $request->validated();
        $image = $request->file('image');
        unset($data['image']);
        $data['remove_image'] = $request->boolean('remove_image');
        $this->products->update($product, $data, $image);

        return redirect()
            ->route('gh.products.index')
            ->with('success', __('gh.flash.product_updated'));
    }

    public function destroy(GhProduct $product): RedirectResponse
    {
        $this->authorize('delete', $product);
        $this->products->delete($product);

        return redirect()
            ->route('gh.products.index')
            ->with('success', __('gh.flash.product_deleted'));
    }

    public function image(GhProduct $product): StreamedResponse
    {
        $this->authorize('viewImage', $product);
        abort_unless($product->image_path && Storage::disk('local')->exists($product->image_path), 404);

        return Storage::disk('local')->response($product->image_path, null, [
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
