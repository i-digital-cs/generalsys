<?php

namespace App\Http\Controllers\Gross;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gross\ImportGrossItemRequest;
use App\Http\Requests\Gross\StoreGrossItemRequest;
use App\Http\Requests\Gross\UpdateGrossItemRequest;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossItemAttribute;
use App\Services\Gross\CategoryService;
use App\Services\Gross\ItemAttributeService;
use App\Services\Gross\ItemService;
use App\Services\Gross\PieceLocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemController extends Controller
{
    public function __construct(
        private readonly ItemService $items,
        private readonly CategoryService $categories,
        private readonly ItemAttributeService $attributes,
        private readonly PieceLocationService $locations,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GrossItem::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'color_id' => $request->query('color_id'),
            'size_id' => $request->query('size_id'),
            'model_id' => $request->query('model_id'),
            'category_id' => $request->query('category_id'),
            'low_stock' => $request->boolean('low_stock'),
        ];

        return view('gross.items.index', [
            'items' => $this->items->paginate($filters),
            'filters' => $filters,
            'colors' => $this->attributes->listByKind(GrossItemAttribute::KIND_COLOR),
            'sizes' => $this->attributes->listByKind(GrossItemAttribute::KIND_SIZE),
            'models' => $this->attributes->listByKind(GrossItemAttribute::KIND_MODEL),
            'categories' => $this->categories->roots(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', GrossItem::class);

        return view('gross.items.create', $this->formData());
    }

    public function store(StoreGrossItemRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['images', 'remove_image_ids']);
        $files = $request->file('images', []) ?? [];
        if (! is_array($files)) {
            $files = [$files];
        }

        $item = $this->items->create($data, $files);

        return redirect()
            ->route('gross.items.show', $item)
            ->with('success', __('gross.flash.item_created'));
    }

    public function show(GrossItem $item): View
    {
        $this->authorize('view', $item);
        $item->load([
            'category',
            'subcategory',
            'balance',
            'colorOption',
            'sizeOption',
            'modelOption',
            'images',
        ]);

        return view('gross.items.show', [
            'item' => $item,
            'courierBreakdown' => $this->locations->courierBreakdownByItemIds([$item->id])[$item->id] ?? [],
            'courierSummary' => $this->locations->courierSummaryByItemIds([$item->id])[$item->id] ?? [
                'awaiting_delivery' => 0,
                'refused_with_courier' => 0,
                'total_with_courier' => 0,
            ],
            'totalTrackedPieces' => $this->locations->totalTrackedPieces($item->balance),
        ]);
    }

    public function edit(GrossItem $item): View
    {
        $this->authorize('update', $item);
        $item->load('images');

        return view('gross.items.edit', array_merge($this->formData(), ['item' => $item]));
    }

    public function update(UpdateGrossItemRequest $request, GrossItem $item): RedirectResponse
    {
        $data = $request->safe()->except(['images', 'remove_image_ids']);
        $files = $request->file('images', []) ?? [];
        if (! is_array($files)) {
            $files = [$files];
        }
        $removeIds = array_map('intval', (array) $request->input('remove_image_ids', []));

        $this->items->update($item, $data, $files, $removeIds);

        return redirect()
            ->route('gross.items.show', $item)
            ->with('success', __('gross.flash.item_updated'));
    }

    public function template(): BinaryFileResponse|StreamedResponse
    {
        $this->authorize('create', GrossItem::class);

        $path = storage_path('app/tmp/gross-items-template.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        $this->items->writeTemplate($path);

        return response()->download(
            $path,
            'gross-items-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function import(ImportGrossItemRequest $request): RedirectResponse
    {
        $path = $request->file('file')->getRealPath();
        $result = $this->items->importFromPath((string) $path);

        $message = __('gross.flash.item_imported', [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
        ]);

        $redirect = redirect()->route('gross.items.index');

        if ($result['errors'] !== []) {
            return $redirect
                ->with('warning', $message)
                ->withErrors(['import' => $result['errors']]);
        }

        return $redirect->with('success', $message);
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $grouped = $this->attributes->grouped();

        return [
            'categories' => $this->categories->allFlat(),
            'nameSuggestions' => $this->items->nameSuggestions(),
            'colors' => $grouped[GrossItemAttribute::KIND_COLOR],
            'sizes' => $grouped[GrossItemAttribute::KIND_SIZE],
            'models' => $grouped[GrossItemAttribute::KIND_MODEL],
        ];
    }
}
