<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossCategory;
use App\Models\Gross\GrossItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CategoryService
{
    public const IMPORT_MAX_ROWS = 500;

    /** @return Collection<int, GrossCategory> */
    public function roots(): Collection
    {
        return GrossCategory::query()
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('name_en')
            ->get();
    }

    /** @return Collection<int, GrossCategory> */
    public function allFlat(): Collection
    {
        return GrossCategory::query()
            ->with('parent')
            ->orderBy('name_en')
            ->get();
    }

    /**
     * Flat list for the admin page (optional search).
     *
     * @return Collection<int, GrossCategory>
     */
    public function searchable(string $query = ''): Collection
    {
        $q = GrossCategory::query()
            ->with('parent')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name_en');

        $query = trim($query);
        if ($query !== '') {
            $like = '%'.$query.'%';
            $q->where(function ($inner) use ($like) {
                $inner->where('name_ar', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhereHas('parent', function ($parent) use ($like) {
                        $parent->where('name_ar', 'like', $like)
                            ->orWhere('name_en', 'like', $like);
                    });
            });
        }

        return $q->get();
    }

    /**
     * @param  array{name_ar: string, name_en: string, parent_id?: int|null}  $data
     */
    public function create(array $data): GrossCategory
    {
        $parentId = $this->normalizeParentId($data['parent_id'] ?? null);

        return GrossCategory::query()->create([
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'parent_id' => $parentId,
        ]);
    }

    /**
     * @param  array{name_ar: string, name_en: string, parent_id?: int|null}  $data
     */
    public function update(GrossCategory $category, array $data): GrossCategory
    {
        $parentId = array_key_exists('parent_id', $data)
            ? $this->normalizeParentId($data['parent_id'], $category->id)
            : $category->parent_id;

        $category->update([
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'parent_id' => $parentId,
        ]);

        return $category->refresh();
    }

    public function delete(GrossCategory $category): void
    {
        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => [__('gross.errors.category_has_children')],
            ]);
        }

        $inUse = GrossItem::query()
            ->where('category_id', $category->id)
            ->orWhere('subcategory_id', $category->id)
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'category' => [__('gross.errors.category_in_use')],
            ]);
        }

        $category->delete();
    }

    public function writeTemplate(string $absolutePath): void
    {
        $sheet = new Spreadsheet;
        $active = $sheet->getActiveSheet();
        $active->setTitle('categories');
        $active->fromArray([
            ['name_ar', 'name_en', 'parent_en'],
            ['ملابس', 'Clothing', ''],
            ['فساتين', 'Dresses', 'Clothing'],
            ['أحذية', 'Shoes', ''],
            ['رياضي', 'Sport', 'Shoes'],
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

        $mains = [];
        $subs = [];
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $nameAr = trim((string) ($row['name_ar'] ?? ''));
            $nameEn = trim((string) ($row['name_en'] ?? ''));
            $parentEn = trim((string) ($row['parent_en'] ?? ''));

            if ($nameAr === '' && $nameEn === '' && $parentEn === '') {
                continue;
            }

            if ($nameAr === '' || $nameEn === '' || mb_strlen($nameAr) > 120 || mb_strlen($nameEn) > 120) {
                $errors[] = __('gross.errors.category_import_names', ['row' => $line]);

                continue;
            }

            $payload = compact('nameAr', 'nameEn', 'parentEn', 'line');
            if ($parentEn === '') {
                $mains[] = $payload;
            } else {
                $subs[] = $payload;
            }
        }

        foreach (array_merge($mains, $subs) as $payload) {
            $result = $this->importOneRow($payload);
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
     * @param  array{nameAr: string, nameEn: string, parentEn: string, line: int}  $payload
     */
    private function importOneRow(array $payload): string
    {
        $parentId = null;
        if ($payload['parentEn'] !== '') {
            $parent = GrossCategory::query()
                ->whereNull('parent_id')
                ->where('name_en', $payload['parentEn'])
                ->first();

            if ($parent === null) {
                return __('gross.errors.category_import_parent', [
                    'row' => $payload['line'],
                    'parent' => $payload['parentEn'],
                ]);
            }
            $parentId = $parent->id;
        }

        $exists = GrossCategory::query()
            ->where('name_en', $payload['nameEn'])
            ->where(function ($q) use ($parentId) {
                if ($parentId === null) {
                    $q->whereNull('parent_id');
                } else {
                    $q->where('parent_id', $parentId);
                }
            })
            ->exists();

        if ($exists) {
            return 'skipped';
        }

        GrossCategory::query()->create([
            'name_ar' => $payload['nameAr'],
            'name_en' => $payload['nameEn'],
            'parent_id' => $parentId,
        ]);

        return 'created';
    }

    private function normalizeParentId(mixed $parentId, ?int $selfId = null): ?int
    {
        if ($parentId === null || $parentId === '') {
            return null;
        }

        $parentId = (int) $parentId;
        if ($selfId !== null && $parentId === $selfId) {
            throw ValidationException::withMessages([
                'parent_id' => [__('gross.errors.category_parent_invalid')],
            ]);
        }

        $parent = GrossCategory::query()->find($parentId);
        if ($parent === null || $parent->parent_id !== null) {
            throw ValidationException::withMessages([
                'parent_id' => [__('gross.errors.category_parent_invalid')],
            ]);
        }

        return $parentId;
    }

    /**
     * @return list<array{name_ar: string, name_en: string, parent_en: string}>
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
        $arIdx = array_search('name_ar', $header, true);
        $enIdx = array_search('name_en', $header, true);
        $parentIdx = array_search('parent_en', $header, true);

        if ($arIdx === false || $enIdx === false || $parentIdx === false) {
            throw ValidationException::withMessages([
                'file' => [__('gross.errors.category_import_headers')],
            ]);
        }

        if (count($matrix) > self::IMPORT_MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => [__('gross.errors.category_import_max', ['max' => self::IMPORT_MAX_ROWS])],
            ]);
        }

        $out = [];
        foreach ($matrix as $row) {
            $out[] = [
                'name_ar' => (string) ($row[$arIdx] ?? ''),
                'name_en' => (string) ($row[$enIdx] ?? ''),
                'parent_en' => (string) ($row[$parentIdx] ?? ''),
            ];
        }

        return $out;
    }

    private function normalizeHeader(string $raw): string
    {
        $key = mb_strtolower(trim($raw));
        $aliases = [
            'name_ar' => 'name_ar',
            'name ar' => 'name_ar',
            'ar' => 'name_ar',
            'name_en' => 'name_en',
            'name en' => 'name_en',
            'en' => 'name_en',
            'parent_en' => 'parent_en',
            'parent' => 'parent_en',
            'parent en' => 'parent_en',
            'الاسم_عربي' => 'name_ar',
            'الاسم عربي' => 'name_ar',
            'الاسم_انجليزي' => 'name_en',
            'الاسم انجليزي' => 'name_en',
            'التصنيف الرئيسي' => 'parent_en',
        ];

        return $aliases[$key] ?? $key;
    }
}
