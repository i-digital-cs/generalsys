<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossItemAttribute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ItemAttributeService
{
    public const IMPORT_MAX_ROWS = 500;

    /** @return Collection<int, GrossItemAttribute> */
    public function listByKind(string $kind): Collection
    {
        return GrossItemAttribute::query()
            ->where('kind', $kind)
            ->orderBy('name_en')
            ->get();
    }

    /**
     * @return array{COLOR: Collection<int, GrossItemAttribute>, SIZE: Collection<int, GrossItemAttribute>, MODEL: Collection<int, GrossItemAttribute>}
     */
    public function grouped(string $query = ''): array
    {
        $q = GrossItemAttribute::query()->orderBy('name_en');

        $query = trim($query);
        if ($query !== '') {
            $like = '%'.$query.'%';
            $q->where(function ($inner) use ($like) {
                $inner->where('name_ar', 'like', $like)
                    ->orWhere('name_en', 'like', $like);
            });
        }

        $all = $q->get()->groupBy('kind');

        return [
            GrossItemAttribute::KIND_COLOR => $all->get(GrossItemAttribute::KIND_COLOR, collect()),
            GrossItemAttribute::KIND_SIZE => $all->get(GrossItemAttribute::KIND_SIZE, collect()),
            GrossItemAttribute::KIND_MODEL => $all->get(GrossItemAttribute::KIND_MODEL, collect()),
        ];
    }

    /**
     * @param  array{kind: string, name_ar: string, name_en: string}  $data
     */
    public function create(array $data): GrossItemAttribute
    {
        $this->assertUnique($data['kind'], $data['name_en']);

        return GrossItemAttribute::query()->create([
            'kind' => $data['kind'],
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
        ]);
    }

    /**
     * @param  array{name_ar: string, name_en: string}  $data
     */
    public function update(GrossItemAttribute $attribute, array $data): GrossItemAttribute
    {
        $this->assertUnique($attribute->kind, $data['name_en'], $attribute->id);

        return DB::transaction(function () use ($attribute, $data) {
            $attribute->update([
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'],
            ]);

            $this->syncItemLabels($attribute->fresh());

            return $attribute->refresh();
        });
    }

    /** Keep denormalized item color/size/model strings in sync after rename. */
    private function syncItemLabels(GrossItemAttribute $attribute): void
    {
        $column = match ($attribute->kind) {
            GrossItemAttribute::KIND_COLOR => 'color',
            GrossItemAttribute::KIND_SIZE => 'size',
            GrossItemAttribute::KIND_MODEL => 'model',
            default => null,
        };

        if ($column === null) {
            return;
        }

        GrossItem::query()
            ->where($column.'_id', $attribute->id)
            ->update([$column => $attribute->name_en]);
    }

    public function delete(GrossItemAttribute $attribute): void
    {
        $inUse = $attribute->itemsAsColor()->exists()
            || $attribute->itemsAsSize()->exists()
            || $attribute->itemsAsModel()->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'attribute' => [__('gross.errors.attribute_in_use')],
            ]);
        }

        $attribute->delete();
    }

    public function writeTemplate(string $absolutePath): void
    {
        $sheet = new Spreadsheet;
        $active = $sheet->getActiveSheet();
        $active->setTitle('attributes');
        $active->fromArray([
            ['kind', 'name_ar', 'name_en'],
            // Add as many rows as you need — one row = one value (mix COLOR / SIZE / MODEL freely)
            ['COLOR', 'كحلي', 'Navy'],
            ['COLOR', 'أسود', 'Black'],
            ['COLOR', 'أبيض', 'White'],
            ['SIZE', 'صغير', 'S'],
            ['SIZE', 'وسط', 'M'],
            ['SIZE', 'كبير', 'L'],
            ['MODEL', 'كلاسيك', 'Classic'],
            ['MODEL', 'مودرن', 'Modern'],
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
            $line = $index + 2; // header is row 1
            $kind = $this->normalizeKind((string) ($row['kind'] ?? ''));
            $nameAr = trim((string) ($row['name_ar'] ?? ''));
            $nameEn = trim((string) ($row['name_en'] ?? ''));

            if ($kind === null && $nameAr === '' && $nameEn === '') {
                continue;
            }

            if ($kind === null) {
                $errors[] = __('gross.errors.attribute_import_kind', ['row' => $line]);

                continue;
            }

            if ($nameAr === '' || $nameEn === '') {
                $errors[] = __('gross.errors.attribute_import_names', ['row' => $line]);

                continue;
            }

            if (mb_strlen($nameAr) > 120 || mb_strlen($nameEn) > 120) {
                $errors[] = __('gross.errors.attribute_import_names', ['row' => $line]);

                continue;
            }

            if ($this->exists($kind, $nameEn)) {
                $skipped++;

                continue;
            }

            GrossItemAttribute::query()->create([
                'kind' => $kind,
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
            ]);
            $created++;
        }

        return compact('created', 'skipped', 'errors');
    }

    private function assertUnique(string $kind, string $nameEn, ?int $ignoreId = null): void
    {
        if ($this->exists($kind, $nameEn, $ignoreId)) {
            throw ValidationException::withMessages([
                'name_en' => [__('gross.errors.attribute_duplicate')],
            ]);
        }
    }

    private function exists(string $kind, string $nameEn, ?int $ignoreId = null): bool
    {
        $q = GrossItemAttribute::query()
            ->where('kind', $kind)
            ->where('name_en', $nameEn);

        if ($ignoreId !== null) {
            $q->whereKeyNot($ignoreId);
        }

        return $q->exists();
    }

    private function normalizeKind(string $raw): ?string
    {
        $key = mb_strtoupper(trim($raw));
        $map = [
            'COLOR' => GrossItemAttribute::KIND_COLOR,
            'COLOUR' => GrossItemAttribute::KIND_COLOR,
            'SIZE' => GrossItemAttribute::KIND_SIZE,
            'MODEL' => GrossItemAttribute::KIND_MODEL,
            'لون' => GrossItemAttribute::KIND_COLOR,
            'مقاس' => GrossItemAttribute::KIND_SIZE,
            'موديل' => GrossItemAttribute::KIND_MODEL,
        ];

        return $map[$key] ?? $map[trim($raw)] ?? null;
    }

    /**
     * @return list<array{kind: string, name_ar: string, name_en: string}>
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
        $kindIdx = array_search('kind', $header, true);
        $arIdx = array_search('name_ar', $header, true);
        $enIdx = array_search('name_en', $header, true);

        if ($kindIdx === false || $arIdx === false || $enIdx === false) {
            throw ValidationException::withMessages([
                'file' => [__('gross.errors.attribute_import_headers')],
            ]);
        }

        if (count($matrix) > self::IMPORT_MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => [__('gross.errors.attribute_import_max', ['max' => self::IMPORT_MAX_ROWS])],
            ]);
        }

        $out = [];
        foreach ($matrix as $row) {
            $out[] = [
                'kind' => (string) ($row[$kindIdx] ?? ''),
                'name_ar' => (string) ($row[$arIdx] ?? ''),
                'name_en' => (string) ($row[$enIdx] ?? ''),
            ];
        }

        return $out;
    }

    private function normalizeHeader(string $raw): string
    {
        $key = mb_strtolower(trim($raw));
        $aliases = [
            'kind' => 'kind',
            'type' => 'kind',
            'name_ar' => 'name_ar',
            'name ar' => 'name_ar',
            'ar' => 'name_ar',
            'name_en' => 'name_en',
            'name en' => 'name_en',
            'en' => 'name_en',
            'النوع' => 'kind',
            'الاسم_عربي' => 'name_ar',
            'الاسم عربي' => 'name_ar',
            'الاسم_انجليزي' => 'name_en',
            'الاسم انجليزي' => 'name_en',
        ];

        return $aliases[$key] ?? $key;
    }
}
