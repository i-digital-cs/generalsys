<?php

namespace App\Services\GeneralHouse\Import\Importers;

use App\Models\GeneralHouse\GhTreatment;
use App\Services\GeneralHouse\Catalog\GhTreatmentService;

class TreatmentImporter extends AbstractGhImporter
{
    public function __construct(private readonly GhTreatmentService $treatments) {}

    public function entityKey(): string
    {
        return 'treatments';
    }

    public function modelClass(): string
    {
        return GhTreatment::class;
    }

    public function templateFilename(): string
    {
        return 'general-house-treatments-example.xlsx';
    }

    public function headers(): array
    {
        return ['name_ar', 'name_en', 'status', 'sort_order'];
    }

    public function requiredHeaders(): array
    {
        return ['name_ar', 'name_en'];
    }

    public function exampleRows(): array
    {
        return [
            ['كي فقط', 'Iron Only', 'ACTIVE', '1'],
            ['غسيل وكي', 'Wash & Iron', 'ACTIVE', '2'],
            ['تنظيف جاف', 'Dry Clean', 'ACTIVE', '3'],
            ['إزالة البقع', 'Stain Removal', 'ACTIVE', '4'],
            ['تبخير', 'Steam Press', 'ACTIVE', '5'],
            ['تعطير', 'Perfume Finish', 'INACTIVE', '6'],
        ];
    }

    public function instructionLines(): array
    {
        return [
            __('gh.import.sheet.treatments_intro'),
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
        return GhTreatment::query()->whereRaw('LOWER(name_en) = ?', [$this->lower($payload['name_en'])])->exists();
    }

    protected function persist(array $payload, array $context, int $row, array &$imageErrors): void
    {
        $this->treatments->create($payload);
    }
}
