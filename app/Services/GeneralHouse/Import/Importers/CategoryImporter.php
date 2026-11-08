<?php

namespace App\Services\GeneralHouse\Import\Importers;

use App\Models\GeneralHouse\GhServiceCategory;
use App\Services\GeneralHouse\Catalog\GhCategoryService;

class CategoryImporter extends AbstractGhImporter
{
    public function __construct(private readonly GhCategoryService $categories) {}

    public function entityKey(): string
    {
        return 'categories';
    }

    public function modelClass(): string
    {
        return GhServiceCategory::class;
    }

    public function templateFilename(): string
    {
        return 'general-house-service-categories-example.xlsx';
    }

    public function headers(): array
    {
        return ['name_ar', 'name_en', 'description_ar', 'description_en', 'status', 'sort_order'];
    }

    public function requiredHeaders(): array
    {
        return ['name_ar', 'name_en'];
    }

    public function exampleRows(): array
    {
        return [
            ['كندورة', 'Kandura', 'الكنادير والثياب الرجالية', 'Men kanduras and thobes', 'ACTIVE', '1'],
            ['عبايات', 'Abaya', 'عبايات نسائية', 'Women abayas', 'ACTIVE', '2'],
            ['بدلات', 'Suits', 'بدلات وجاكيتات', 'Suits and jackets', 'ACTIVE', '3'],
            ['مفروشات', 'Home Linen', 'شراشف ومفارش', 'Bed sheets and covers', 'ACTIVE', '4'],
            ['ستائر', 'Curtains', 'ستائر المنزل', 'Household curtains', 'ACTIVE', '5'],
            ['سجاد', 'Carpets', '', '', 'INACTIVE', '6'],
        ];
    }

    public function instructionLines(): array
    {
        return [
            __('gh.import.sheet.categories_intro'),
            __('gh.import.sheet.required', ['columns' => 'name_ar, name_en']),
            __('gh.import.sheet.duplicates', ['key' => 'name_en']),
            __('gh.import.sheet.status_values'),
            __('gh.import.sheet.max_rows', ['max' => 500]),
        ];
    }

    public function referenceRows(): array
    {
        return [
            ['status', 'sort_order'],
            ['ACTIVE', '0 - 999999'],
            ['INACTIVE', ''],
        ];
    }

    protected function parseRow(array $data, array $context): array
    {
        return [
            'name_ar' => $this->text($data, 'name_ar', 120, true),
            'name_en' => $this->text($data, 'name_en', 120, true),
            'description_ar' => $this->text($data, 'description_ar', 500),
            'description_en' => $this->text($data, 'description_en', 500),
            'status' => $this->status($data),
            'sort_order' => $this->sortOrder($data),
        ];
    }

    protected function duplicateKey(array $payload): string
    {
        return $this->lower($payload['name_en']);
    }

    protected function existsInDatabase(array $payload): bool
    {
        return GhServiceCategory::query()->whereRaw('LOWER(name_en) = ?', [$this->lower($payload['name_en'])])->exists();
    }

    protected function persist(array $payload, array $context, int $row, array &$imageErrors): void
    {
        $this->categories->create($payload);
    }
}
