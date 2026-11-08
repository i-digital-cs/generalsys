<?php

namespace App\Services\GeneralHouse\Import\Importers;

use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhServiceCategory;
use App\Services\GeneralHouse\Catalog\GhProductService;
use App\Services\GeneralHouse\Import\GhImageImportException;
use App\Services\GeneralHouse\Import\GhImageValidator;
use App\Services\GeneralHouse\Import\GhImportRowException;
use App\Services\GeneralHouse\Import\GhSafeImageFetcher;
use App\Support\GeneralHouse\GhCatalogStatus;

class ProductImporter extends AbstractGhImporter
{
    public function __construct(
        private readonly GhProductService $products,
        private readonly GhSafeImageFetcher $fetcher,
        private readonly GhImageValidator $images,
    ) {}

    public function entityKey(): string
    {
        return 'products';
    }

    public function modelClass(): string
    {
        return GhProduct::class;
    }

    public function templateFilename(): string
    {
        return 'general-house-products-example.xlsx';
    }

    public function headers(): array
    {
        return ['code', 'name_ar', 'name_en', 'category_name_en', 'status', 'sort_order', 'image_url', 'image_filename'];
    }

    public function requiredHeaders(): array
    {
        return ['code', 'name_ar', 'name_en', 'category_name_en'];
    }

    public function exampleRows(): array
    {
        $categories = $this->liveCategoryNames();
        if ($categories === []) {
            return $this->fallbackExampleRows();
        }

        $rows = [];
        $taken = [];
        for ($i = 0; $i < 6; $i++) {
            $category = $categories[$i % count($categories)];
            $rows[] = [
                $this->freeExampleCode($category, $i + 1, $taken),
                'منتج تجريبي '.($i + 1),
                'Sample item '.($i + 1),
                $category,
                'ACTIVE',
                (string) ($i + 1),
                '',
                '',
            ];
        }

        return $rows;
    }

    /** @return list<string> */
    private function liveCategoryNames(): array
    {
        return GhServiceCategory::query()
            ->where('status', GhCatalogStatus::ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->limit(6)
            ->pluck('name_en')
            ->all();
    }

    /** @param  list<string>  $taken */
    private function freeExampleCode(string $category, int $seq, array &$taken): string
    {
        $prefix = strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', $category), 0, 3)) ?: 'PRD';
        $candidate = sprintf('%s-%03d', $prefix, $seq);
        while (in_array($candidate, $taken, true) || GhProduct::query()->where('code', $candidate)->exists()) {
            $seq += 10;
            $candidate = sprintf('%s-%03d', $prefix, $seq);
        }
        $taken[] = $candidate;

        return $candidate;
    }

    /** @return list<list<string>> */
    private function fallbackExampleRows(): array
    {
        return [
            ['KAN-001', 'كندورة رجالي', 'Men Kandura', 'Kandura', 'ACTIVE', '1', '', 'kan-001.jpg'],
            ['ABA-001', 'عباية سادة', 'Plain Abaya', 'Abaya', 'ACTIVE', '2', 'https://example.com/images/aba-001.jpg', ''],
            ['SUT-001', 'بدلة رسمية', 'Formal Suit', 'Suits', 'ACTIVE', '3', '', ''],
            ['LIN-001', 'شرشف سرير', 'Bed Sheet', 'Home Linen', 'ACTIVE', '4', '', ''],
            ['CUR-001', 'ستارة', 'Curtain Panel', 'Curtains', 'ACTIVE', '5', '', ''],
            ['CAR-001', 'سجادة صغيرة', 'Small Carpet', 'Carpets', 'INACTIVE', '6', '', ''],
        ];
    }

    public function instructionLines(): array
    {
        return [
            __('gh.import.sheet.products_intro'),
            __('gh.import.sheet.import_categories_first'),
            $this->liveCategoryNames() === []
                ? __('gh.import.sheet.examples_fallback')
                : __('gh.import.sheet.examples_live'),
            __('gh.import.sheet.required', ['columns' => 'code, name_ar, name_en, category_name_en']),
            __('gh.import.sheet.duplicates', ['key' => 'code']),
            __('gh.import.sheet.category_must_exist'),
            __('gh.import.sheet.image_rules'),
            __('gh.import.sheet.status_values'),
            __('gh.import.sheet.max_rows', ['max' => 500]),
        ];
    }

    public function referenceRows(): array
    {
        $rows = [['category_name_en', 'category_name_ar', 'status']];
        foreach (GhServiceCategory::query()->orderBy('sort_order')->orderBy('name_en')->limit(500)->get() as $category) {
            $rows[] = [$category->name_en, $category->name_ar, $category->status];
        }

        return $rows;
    }

    protected function parseRow(array $data, array $context): array
    {
        $code = strtoupper($this->text($data, 'code', 40, true));
        if (preg_match('/^[A-Z0-9_-]+$/', $code) !== 1) {
            throw new GhImportRowException(__('gh.import.errors.invalid_code'));
        }

        $categoryName = $this->text($data, 'category_name_en', 120, true);
        $categoryId = GhServiceCategory::query()->whereRaw('LOWER(name_en) = ?', [$this->lower($categoryName)])->value('id');
        if ($categoryId === null) {
            throw new GhImportRowException(__('gh.import.errors.category_not_found', ['name' => $categoryName]));
        }

        return [
            'code' => $code,
            'name_ar' => $this->text($data, 'name_ar', 150, true),
            'name_en' => $this->text($data, 'name_en', 150, true),
            'category_id' => (int) $categoryId,
            'status' => $this->status($data),
            'sort_order' => $this->sortOrder($data),
        ] + $this->imageSource($data);
    }

    /**
     * @param  array<string, string>  $data
     * @return array{image_url: ?string, image_filename: ?string}
     */
    private function imageSource(array $data): array
    {
        $url = $this->text($data, 'image_url', 500);
        $filename = $this->text($data, 'image_filename', 150);
        if ($url !== null && $filename !== null) {
            throw new GhImportRowException(__('gh.import.errors.image_both'));
        }
        if ($url !== null) {
            try {
                $this->fetcher->assertUrlAllowed($url);
            } catch (GhImageImportException $e) {
                throw new GhImportRowException($e->getMessage());
            }
        }
        if ($filename !== null && ($filename !== basename(str_replace('\\', '/', $filename)) || ! preg_match('/\.(jpe?g|png|webp)$/i', $filename))) {
            throw new GhImportRowException(__('gh.import.errors.image_filename_invalid'));
        }

        return ['image_url' => $url, 'image_filename' => $filename];
    }

    protected function duplicateKey(array $payload): string
    {
        return $payload['code'];
    }

    protected function existsInDatabase(array $payload): bool
    {
        return GhProduct::query()->where('code', $payload['code'])->exists();
    }

    protected function persist(array $payload, array $context, int $row, array &$imageErrors): void
    {
        $product = $this->products->create($payload);

        if ($payload['image_url'] === null && $payload['image_filename'] === null) {
            return;
        }

        try {
            $this->attachImage($product, $payload, $context);
        } catch (GhImageImportException $e) {
            $imageErrors[] = __('gh.import.errors.image_row', ['row' => $row, 'code' => $product->code, 'message' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    private function attachImage(GhProduct $product, array $payload, array $context): void
    {
        if ($payload['image_url'] !== null) {
            $fetched = $this->fetcher->fetch($payload['image_url']);
            try {
                $this->products->attachStoredImage($product, $fetched['path'], $fetched['ext']);
            } finally {
                @unlink($fetched['path']);
            }

            return;
        }

        $path = $context['archive_files'][strtolower($payload['image_filename'])] ?? null;
        if ($path === null || ! is_file($path)) {
            throw new GhImageImportException(__('gh.import.errors.image_not_in_zip', ['name' => $payload['image_filename']]));
        }
        $this->products->attachStoredImage($product, $path, $this->images->extensionFor($path));
    }
}
