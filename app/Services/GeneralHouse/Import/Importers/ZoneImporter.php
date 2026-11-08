<?php

namespace App\Services\GeneralHouse\Import\Importers;

use App\Models\GeneralHouse\GhZone;
use App\Services\GeneralHouse\Customers\GhZoneService;
use App\Support\GeneralHouse\GhMinOrderPolicy;

class ZoneImporter extends AbstractGhImporter
{
    public function __construct(private readonly GhZoneService $zones) {}

    public function entityKey(): string
    {
        return 'zones';
    }

    public function modelClass(): string
    {
        return GhZone::class;
    }

    public function templateFilename(): string
    {
        return 'general-house-zones-example.xlsx';
    }

    public function headers(): array
    {
        return ['name_ar', 'name_en', 'min_order_amount', 'min_order_policy', 'status', 'sort_order'];
    }

    public function requiredHeaders(): array
    {
        return ['name_ar', 'name_en'];
    }

    public function exampleRows(): array
    {
        return [
            ['مدينة دبي', 'Dubai City', '50', 'VALIDATION_ONLY', 'ACTIVE', '1'],
            ['الشارقة', 'Sharjah', '60', 'VALIDATION_ONLY', 'ACTIVE', '2'],
            ['عجمان', 'Ajman', '70', 'VALIDATION_ONLY', 'ACTIVE', '3'],
            ['أبوظبي', 'Abu Dhabi', '80', 'SURCHARGE', 'ACTIVE', '4'],
            ['العين', 'Al Ain', '90', 'VALIDATION_ONLY', 'ACTIVE', '5'],
            ['رأس الخيمة', 'Ras Al Khaimah', '100', 'VALIDATION_ONLY', 'INACTIVE', '6'],
        ];
    }

    public function instructionLines(): array
    {
        return [
            __('gh.import.sheet.zones_intro'),
            __('gh.import.sheet.required', ['columns' => 'name_ar, name_en']),
            __('gh.import.sheet.duplicates', ['key' => 'name_en']),
            __('gh.import.sheet.zone_policy', ['default' => GhMinOrderPolicy::DEFAULT]),
            __('gh.import.sheet.status_values'),
            __('gh.import.sheet.max_rows', ['max' => 500]),
        ];
    }

    public function referenceRows(): array
    {
        return [
            ['min_order_policy', 'status'],
            [GhMinOrderPolicy::VALIDATION_ONLY, 'ACTIVE'],
            [GhMinOrderPolicy::SURCHARGE, 'INACTIVE'],
        ];
    }

    protected function parseRow(array $data, array $context): array
    {
        return [
            'name_ar' => $this->text($data, 'name_ar', 120, true),
            'name_en' => $this->text($data, 'name_en', 120, true),
            'min_order_amount' => $this->money($data, 'min_order_amount', false) ?? '0.00',
            'min_order_policy' => $this->enum($data, 'min_order_policy', GhMinOrderPolicy::ALL, GhMinOrderPolicy::DEFAULT),
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
        return GhZone::query()->whereRaw('LOWER(name_en) = ?', [$this->lower($payload['name_en'])])->exists();
    }

    protected function persist(array $payload, array $context, int $row, array &$imageErrors): void
    {
        $this->zones->create($payload);
    }
}
