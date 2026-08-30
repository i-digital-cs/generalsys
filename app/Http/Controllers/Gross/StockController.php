<?php

namespace App\Http\Controllers\Gross;

use App\Exceptions\GrossException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gross\AdjustGrossStockRequest;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossStockMovement;
use App\Services\Gross\ItemService;
use App\Services\Gross\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    public function __construct(
        private readonly ItemService $items,
        private readonly StockService $stock,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GrossItem::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'low_stock' => $request->boolean('low_stock'),
        ];

        return view('gross.stock.index', [
            'items' => $this->items->paginate($filters),
            'adjustItems' => $this->items->activeForSelect(),
            'filters' => $filters,
            'canAdjust' => auth()->user()?->can('adjustStock', GrossItem::class) ?? false,
        ]);
    }

    public function movements(Request $request): View
    {
        $this->authorize('viewAny', GrossItem::class);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => trim((string) $request->query('type', '')),
            'item_id' => $request->query('item_id'),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
        ];

        return view('gross.stock.movements', [
            'movements' => $this->stock->paginateMovements($filters),
            'filters' => $filters,
            'types' => [
                GrossStockMovement::TYPE_ADJUSTMENT_IN,
                GrossStockMovement::TYPE_ADJUSTMENT_OUT,
                GrossStockMovement::TYPE_SALE_OUT,
                GrossStockMovement::TYPE_SALE_RETURN,
                GrossStockMovement::TYPE_REFUSE_RETURN,
                GrossStockMovement::TYPE_LOSS_CONFIRM,
                GrossStockMovement::TYPE_CANCEL_RESTORE,
                GrossStockMovement::TYPE_PURCHASE_IN,
                GrossStockMovement::TYPE_PURCHASE_RETURN,
            ],
            'items' => $this->items->activeForSelect(200),
        ]);
    }

    public function adjust(AdjustGrossStockRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $item = GrossItem::query()->findOrFail($data['item_id']);

        try {
            if ($data['direction'] === 'in') {
                $this->stock->adjustIn($item, (int) $data['qty'], $data['unit'], $request->user(), $data['notes'] ?? null);
            } else {
                $this->stock->adjustOut($item, (int) $data['qty'], $data['unit'], $request->user(), $data['notes'] ?? null);
            }
        } catch (GrossException $e) {
            return back()->withInput()->withErrors(['qty' => __($e->getMessage())]);
        }

        return redirect()
            ->route('gross.stock.index')
            ->with('success', __('gross.flash.stock_adjusted'));
    }
}
