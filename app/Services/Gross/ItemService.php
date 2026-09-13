<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossCategory;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossItemAttribute;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ItemService
{
    public const CODE_PREFIX = 'ITM-';

    public const IMPORT_MAX_ROWS = 500;

    public function __construct(
        private readonly StockService $stock,
        private readonly ItemImageService $images,
    ) {}

    /**
     * @param  array{q?: string, color_id?: int|null, size_id?: int|null, model_id?: int|null, category_id?: int|null, low_stock?: bool|string|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $q = GrossItem::query()
            ->with(['category', 'subcategory', 'balance', 'colorOption', 'sizeOption', 'modelOption', 'images'])
            ->orderBy('code');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $q->where(function ($inner) use ($search) {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%');
            });
        }

        foreach (['color_id', 'size_id', 'model_id', 'category_id'] as $key) {
            if (! empty($filters[$key])) {
                $q->where($key, (int) $filters[$key]);
            }
        }

        if (filter_var($filters['low_stock'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $q->where(function ($inner) {
                $inner->whereDoesntHave('balance')
                    ->orWhereHas('balance', function ($balance) {
                        $balance->whereRaw(
                            'qty_on_hand_pieces <= (select reorder_level from gross_items where id = gross_stock_balances.item_id)'
                        );
                    });
            });
        }

        return $q->paginate(min($perPage, 50))->withQueryString();
    }

    /**
     * Lightweight list for stock adjust dropdowns (ACTIVE only).
     *
     * @return Collection<int, GrossItem>
     */
    public function activeForSelect(int $limit = 500): Collection
    {
        return GrossItem::query()
            ->where('status', GrossItem::STATUS_ACTIVE)
            ->orderBy('code')
            ->limit(min($limit, 500))
            ->get(['id', 'code', 'name']);
    }

    /**
     * Active catalog for sales invoice forms (price + labels).
     *
     * @return Collection<int, GrossItem>
     */
    public function activeForForms(int $limit = 500): Collection
    {
        return GrossItem::query()
            ->where('status', GrossItem::STATUS_ACTIVE)
            ->with(['balance', 'colorOption', 'sizeOption', 'modelOption'])
            ->orderBy('code')
            ->limit(min($limit, 500))
            ->get();
    }

    /**
     * Load specific active items for invoice edit / validation replay (keyed by id).
     *
     * @param  list<int|string>  $ids
     * @return Collection<int, GrossItem>
     */
    public function findForForms(array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return collect();
        }

        return GrossItem::query()
            ->whereIn('id', $ids)
            ->with(['balance', 'colorOption', 'sizeOption', 'modelOption'])
            ->get()
            ->keyBy('id');
    }

    /**
     * Typeahead search for invoice line item pickers (name / code / size / color / model).
     *
     * @return Collection<int, GrossItem>
     */
    public function searchForForms(string $query, int $limit = 25): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }

        $like = '%'.$query.'%';

        return GrossItem::query()
            ->where('status', GrossItem::STATUS_ACTIVE)
            ->with(['balance', 'colorOption', 'sizeOption', 'modelOption'])
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('color', 'like', $like)
                    ->orWhere('size', 'like', $like)
                    ->orWhere('model', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('colorOption', function ($attr) use ($like) {
                        $attr->where('name_ar', 'like', $like)->orWhere('name_en', 'like', $like);
                    })
                    ->orWhereHas('sizeOption', function ($attr) use ($like) {
                        $attr->where('name_ar', 'like', $like)->orWhere('name_en', 'like', $like);
                    })
                    ->orWhereHas('modelOption', function ($attr) use ($like) {
                        $attr->where('name_ar', 'like', $like)->orWhere('name_en', 'like', $like);
                    });
            })
            ->orderBy('code')
            ->limit(min(max($limit, 1), 50))
            ->get();
    }

    /**
     * @return array{id: int, label: string, size: string, sale_price: string|null, buy_price: string, on_hand: int|null}
     */
    public function toFormOption(GrossItem $item): array
    {
        return [
            'id' => $item->id,
            'label' => $this->formLabel($item),
            'size' => $this->itemSizeLabel($item),
            'sale_price' => $item->default_sale_price !== null ? (string) $item->default_sale_price : null,
            'buy_price' => '',
            'on_hand' => $item->balance?->qty_on_hand_pieces !== null
                ? (int) $item->balance->qty_on_hand_pieces
                : null,
        ];
    }

    public function formLabel(GrossItem $item): string
    {
        $bits = [(string) $item->code, (string) $item->name];
        $color = $item->colorOption?->localizedName() ?: trim((string) ($item->color ?? ''));
        $size = $this->itemSizeLabel($item);
        $model = $item->modelOption?->localizedName() ?: trim((string) ($item->model ?? ''));
        if ($color !== '') {
            $bits[] = $color;
        }
        if ($size !== '') {
            $bits[] = $size;
        }
        if ($model !== '') {
            $bits[] = $model;
        }
        $onHand = $item->balance?->qty_on_hand_pieces;
        if ($onHand !== null) {
            $bits[] = '('.$onHand.')';
        }

        return implode(' — ', $bits);
    }

    private function itemSizeLabel(GrossItem $item): string
    {
        return $item->sizeOption?->localizedName() ?: trim((string) ($item->size ?? ''));
    }

    /**
     * Distinct item names for autocomplete.
     *
     * @return list<string>
     */
    public function nameSuggestions(string $query = '', int $limit = 100): array
    {
        $q = GrossItem::query()
            ->whereNotNull('name')
            ->where('name', '!=', '');

        $query = trim($query);
        if ($query !== '') {
            $q->where('name', 'like', '%'.$query.'%');
        }

        return $q->select('name')
            ->distinct()
            ->orderBy('name')
            ->limit(min($limit, 100))
            ->pluck('name')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();
    }

    /**
     * If subcategory is set, force category_id to its parent.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function syncCategoryFromSubcategory(array $data): array
    {
        $subId = $data['subcategory_id'] ?? null;
        if ($subId === null || $subId === '') {
            return $data;
        }

        $parentId = GrossCategory::query()
            ->whereKey((int) $subId)
            ->value('parent_id');

        if ($parentId) {
            $data['category_id'] = (int) $parentId;
        }

        return $data;
    }

    /**
     * Resolve attribute FKs into denormalized color/size/model labels (for invoice snapshots).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function syncAttributeLabels(array $data): array
    {
        $map = [
            'color_id' => 'color',
            'size_id' => 'size',
            'model_id' => 'model',
        ];

        foreach ($map as $fk => $label) {
            $id = $data[$fk] ?? null;
            if ($id === null || $id === '') {
                $data[$fk] = null;
                $data[$label] = null;

                continue;
            }

            $attr = GrossItemAttribute::query()->find((int) $id);
            $data[$fk] = $attr?->id;
            $data[$label] = $attr?->name_en;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $imageFiles
     */
    public function create(array $data, array $imageFiles = []): GrossItem
    {
        $data = $this->syncAttributeLabels($this->syncCategoryFromSubcategory($data));

        return DB::transaction(function () use ($data, $imageFiles) {
            $item = GrossItem::query()->create([
                'code' => $this->nextCode(),
                'name' => $data['name'],
                'color_id' => $data['color_id'] ?? null,
                'size_id' => $data['size_id'] ?? null,
                'model_id' => $data['model_id'] ?? null,
                'color' => $data['color'] ?? null,
                'size' => $data['size'] ?? null,
                'model' => $data['model'] ?? null,
                'description' => $data['description'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'pieces_per_carton' => 1,
                'reorder_level' => $data['reorder_level'] ?? 5,
                'default_sale_price' => $data['default_sale_price'] ?? null,
                'status' => $data['status'] ?? GrossItem::STATUS_ACTIVE,
            ]);

            $this->stock->ensureBalanceRow($item);
            $this->images->attachMany($item, $imageFiles);

            return $item->load('images');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $imageFiles
     * @param  list<int>  $removeImageIds
     */
    public function update(GrossItem $item, array $data, array $imageFiles = [], array $removeImageIds = []): GrossItem
    {
        $data = $this->syncAttributeLabels($this->syncCategoryFromSubcategory($data));

        return DB::transaction(function () use ($item, $data, $imageFiles, $removeImageIds) {
            $this->images->deleteMany($item, $removeImageIds);

            $item->update([
                'name' => $data['name'] ?? $item->name,
                'color_id' => array_key_exists('color_id', $data) ? $data['color_id'] : $item->color_id,
                'size_id' => array_key_exists('size_id', $data) ? $data['size_id'] : $item->size_id,
                'model_id' => array_key_exists('model_id', $data) ? $data['model_id'] : $item->model_id,
                'color' => array_key_exists('color', $data) ? $data['color'] : $item->color,
                'size' => array_key_exists('size', $data) ? $data['size'] : $item->size,
                'model' => array_key_exists('model', $data) ? $data['model'] : $item->model,
                'description' => array_key_exists('description', $data) ? $data['description'] : $item->description,
                'category_id' => array_key_exists('category_id', $data) ? $data['category_id'] : $item->category_id,
                'subcategory_id' => array_key_exists('subcategory_id', $data) ? $data['subcategory_id'] : $item->subcategory_id,
                'reorder_level' => $data['reorder_level'] ?? $item->reorder_level,
                'default_sale_price' => array_key_exists('default_sale_price', $data) ? $data['default_sale_price'] : $item->default_sale_price,
                'status' => $data['status'] ?? $item->status,
            ]);

            $this->images->attachMany($item->fresh(), $imageFiles);

            return $item->fresh(['images']);
        });
    }

    public function writeTemplate(string $absolutePath): void
    {
        $sheet = new Spreadsheet;
        $active = $sheet->getActiveSheet();
        $active->setTitle('items');
        $active->fromArray([
            ['name', 'color_en', 'size_en', 'model_en', 'category_en', 'subcategory_en', 'reorder_level', 'default_sale_price', 'status', 'description'],
            ['Summer Dress', 'Navy', 'L', 'Classic', 'Clothing', 'Dresses', 5, 250.00, 'ACTIVE', 'Example row'],
            ['Sport Shoes', 'Black', 'M', '', 'Shoes', 'Sport', 5, '', 'ACTIVE', ''],
        ]);

        (new Xlsx($sheet))->save($absolutePath);
        $sheet->disconnectWorksheets();
    }

    /**
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public function importFromPath(string $absolutePath): array
    {
        $rows = $this->readImportRows($absolutePath);
        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $result = $this->importOneRow($row, $line);
            if ($result === '') {
                continue;
            }
            if ($result === 'created') {
                $created++;
            } elseif ($result === 'skipped') {
                $skipped++;
            } else {
                $errors[] = $result;
            }
        }

        return compact('created', 'skipped', 'errors');
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importOneRow(array $row, int $line): string
    {
        $name = trim($row['name'] ?? '');
        if ($name === '') {
            if ($this->rowIsBlank($row)) {
                return '';
            }

            return __('gross.errors.item_import_name', ['row' => $line]);
        }

        if (mb_strlen($name) > 191) {
            return __('gross.errors.item_import_name', ['row' => $line]);
        }

        $colorId = $this->resolveAttributeId(GrossItemAttribute::KIND_COLOR, trim($row['color_en'] ?? ''));
        if ($colorId === false) {
            return __('gross.errors.item_import_attr', ['row' => $line, 'kind' => 'color', 'value' => trim($row['color_en'] ?? '')]);
        }

        $sizeId = $this->resolveAttributeId(GrossItemAttribute::KIND_SIZE, trim($row['size_en'] ?? ''));
        if ($sizeId === false) {
            return __('gross.errors.item_import_attr', ['row' => $line, 'kind' => 'size', 'value' => trim($row['size_en'] ?? '')]);
        }

        $modelId = $this->resolveAttributeId(GrossItemAttribute::KIND_MODEL, trim($row['model_en'] ?? ''));
        if ($modelId === false) {
            return __('gross.errors.item_import_attr', ['row' => $line, 'kind' => 'model', 'value' => trim($row['model_en'] ?? '')]);
        }

        $cats = $this->resolveCategories(trim($row['category_en'] ?? ''), trim($row['subcategory_en'] ?? ''));
        if (is_string($cats)) {
            return __('gross.errors.item_import_category', ['row' => $line, 'message' => $cats]);
        }

        $reorder = trim($row['reorder_level'] ?? '');
        $reorderLevel = $reorder === '' ? 5 : (int) $reorder;
        if ($reorder !== '' && (! ctype_digit($reorder) || $reorderLevel < 0)) {
            return __('gross.errors.item_import_reorder', ['row' => $line]);
        }

        $priceRaw = trim($row['default_sale_price'] ?? '');
        $price = null;
        if ($priceRaw !== '') {
            if (! is_numeric($priceRaw) || (float) $priceRaw < 0) {
                return __('gross.errors.item_import_price', ['row' => $line]);
            }
            $price = round((float) $priceRaw, 2);
        }

        $status = mb_strtoupper(trim($row['status'] ?? 'ACTIVE'));
        if ($status === '') {
            $status = GrossItem::STATUS_ACTIVE;
        }
        if (! in_array($status, [GrossItem::STATUS_ACTIVE, GrossItem::STATUS_INACTIVE], true)) {
            return __('gross.errors.item_import_status', ['row' => $line]);
        }

        $exists = GrossItem::query()
            ->where('name', $name)
            ->where(fn ($q) => $colorId === null ? $q->whereNull('color_id') : $q->where('color_id', $colorId))
            ->where(fn ($q) => $sizeId === null ? $q->whereNull('size_id') : $q->where('size_id', $sizeId))
            ->where(fn ($q) => $modelId === null ? $q->whereNull('model_id') : $q->where('model_id', $modelId))
            ->exists();

        if ($exists) {
            return 'skipped';
        }

        $this->create([
            'name' => $name,
            'color_id' => $colorId,
            'size_id' => $sizeId,
            'model_id' => $modelId,
            'category_id' => $cats['category_id'],
            'subcategory_id' => $cats['subcategory_id'],
            'reorder_level' => $reorderLevel,
            'default_sale_price' => $price,
            'status' => $status,
            'description' => trim($row['description'] ?? '') !== '' ? trim($row['description']) : null,
        ]);

        return 'created';
    }

    /** @param  array<string, string>  $row */
    private function rowIsBlank(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function resolveAttributeId(string $kind, string $nameEn): int|false|null
    {
        if ($nameEn === '') {
            return null;
        }

        $id = GrossItemAttribute::query()
            ->where('kind', $kind)
            ->where('name_en', $nameEn)
            ->value('id');

        return $id !== null ? (int) $id : false;
    }

    /**
     * @return array{category_id: int|null, subcategory_id: int|null}|string
     */
    private function resolveCategories(string $categoryEn, string $subcategoryEn): array|string
    {
        $categoryId = null;
        $subcategoryId = null;

        if ($categoryEn !== '') {
            $categoryId = GrossCategory::query()
                ->whereNull('parent_id')
                ->where('name_en', $categoryEn)
                ->value('id');
            if ($categoryId === null) {
                return 'category';
            }
            $categoryId = (int) $categoryId;
        }

        if ($subcategoryEn !== '') {
            $sub = GrossCategory::query()
                ->whereNotNull('parent_id')
                ->where('name_en', $subcategoryEn)
                ->when($categoryId !== null, fn ($q) => $q->where('parent_id', $categoryId))
                ->first();
            if ($sub === null) {
                return 'subcategory';
            }
            $subcategoryId = (int) $sub->id;
            $categoryId = (int) $sub->parent_id;
        }

        return ['category_id' => $categoryId, 'subcategory_id' => $subcategoryId];
    }

    /**
     * @return list<array<string, string>>
     */
    private function readImportRows(string $absolutePath): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();
        $matrix = $sheet->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();

        if ($matrix === []) {
            return [];
        }

        $header = array_map(fn ($v) => $this->normalizeHeader((string) $v), array_shift($matrix));
        $required = ['name', 'color_en', 'size_en', 'model_en', 'category_en', 'subcategory_en', 'reorder_level', 'default_sale_price', 'status', 'description'];
        $indexes = [];
        foreach ($required as $key) {
            $idx = array_search($key, $header, true);
            if ($idx === false) {
                throw ValidationException::withMessages([
                    'file' => [__('gross.errors.item_import_headers')],
                ]);
            }
            $indexes[$key] = $idx;
        }

        if (count($matrix) > self::IMPORT_MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => [__('gross.errors.item_import_max', ['max' => self::IMPORT_MAX_ROWS])],
            ]);
        }

        $out = [];
        foreach ($matrix as $row) {
            $mapped = [];
            foreach ($indexes as $key => $idx) {
                $mapped[$key] = (string) ($row[$idx] ?? '');
            }
            $out[] = $mapped;
        }

        return $out;
    }

    private function normalizeHeader(string $raw): string
    {
        $key = mb_strtolower(trim($raw));
        $aliases = [
            'name' => 'name',
            'item' => 'name',
            'color_en' => 'color_en',
            'color' => 'color_en',
            'size_en' => 'size_en',
            'size' => 'size_en',
            'model_en' => 'model_en',
            'model' => 'model_en',
            'category_en' => 'category_en',
            'category' => 'category_en',
            'subcategory_en' => 'subcategory_en',
            'subcategory' => 'subcategory_en',
            'reorder_level' => 'reorder_level',
            'reorder' => 'reorder_level',
            'default_sale_price' => 'default_sale_price',
            'price' => 'default_sale_price',
            'status' => 'status',
            'description' => 'description',
            'الاسم' => 'name',
            'اللون' => 'color_en',
            'المقاس' => 'size_en',
            'الموديل' => 'model_en',
        ];

        return $aliases[$key] ?? $key;
    }

    private function nextCode(): string
    {
        $last = GrossItem::query()
            ->where('code', 'like', self::CODE_PREFIX.'%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('code');

        $n = 0;
        if (is_string($last) && preg_match('/^'.preg_quote(self::CODE_PREFIX, '/').'(\d+)$/', $last, $matches) === 1) {
            $n = (int) $matches[1];
        }

        do {
            $n++;
            $code = sprintf('%s%05d', self::CODE_PREFIX, $n);
        } while (GrossItem::query()->where('code', $code)->exists());

        return $code;
    }
}
