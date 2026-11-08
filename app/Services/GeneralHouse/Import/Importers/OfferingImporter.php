<?php

namespace App\Services\GeneralHouse\Import\Importers;

use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhTreatment;
use App\Services\GeneralHouse\Catalog\GhOfferingService;
use App\Services\GeneralHouse\Import\GhImportRowException;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhTaxClass;

class OfferingImporter extends AbstractGhImporter
{
    public function __construct(private readonly GhOfferingService $offerings) {}

    public function entityKey(): string
    {
        return 'offerings';
    }

    public function modelClass(): string
    {
        return GhProductOffering::class;
    }

    public function templateFilename(): string
    {
        return 'general-house-offerings-example.xlsx';
    }

    public function headers(): array
    {
        return ['product_code', 'treatment_name_en', 'standard_price', 'tax_class', 'status', 'sort_order'];
    }

    public function requiredHeaders(): array
    {
        return ['product_code', 'treatment_name_en', 'standard_price'];
    }

    public function exampleRows(): array
    {
        $pairs = $this->livePairs();
        if ($pairs === []) {
            return $this->fallbackExampleRows();
        }

        $prices = ['12.00', '6.00', '18.50', '25.00', '30.00', '15.00'];
        $rows = [];
        foreach ($pairs as $i => [$code, $treatment]) {
            $rows[] = [$code, $treatment, $prices[$i % count($prices)], GhTaxClass::STANDARD_RATED, 'ACTIVE', (string) ($i + 1)];
        }

        return $rows;
    }

    /**
     * Active product × active treatment pairs; pairs without an offering yet come first.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function livePairs(): array
    {
        $products = GhProduct::query()->where('status', GhCatalogStatus::ACTIVE)->orderBy('sort_order')->orderBy('code')->limit(20)->pluck('code', 'id');
        $treatments = GhTreatment::query()->where('status', GhCatalogStatus::ACTIVE)->orderBy('sort_order')->orderBy('name_en')->limit(20)->pluck('name_en', 'id');
        if ($products->isEmpty() || $treatments->isEmpty()) {
            return [];
        }

        $existing = GhProductOffering::query()
            ->whereIn('product_id', $products->keys())
            ->get(['product_id', 'treatment_id'])
            ->mapWithKeys(fn ($o) => [$o->product_id.'-'.$o->treatment_id => true]);

        $fresh = [];
        $all = [];
        foreach ($products as $productId => $code) {
            foreach ($treatments as $treatmentId => $name) {
                $pair = [$code, $name];
                $all[] = $pair;
                if (! isset($existing[$productId.'-'.$treatmentId])) {
                    $fresh[] = $pair;
                }
            }
        }

        return array_slice($fresh !== [] ? $fresh : $all, 0, 6);
    }

    /** @return list<list<string>> */
    private function fallbackExampleRows(): array
    {
        return [
            ['KAN-001', 'Wash & Iron', '12.00', 'STANDARD_RATED', 'ACTIVE', '1'],
            ['KAN-001', 'Iron Only', '6.00', 'STANDARD_RATED', 'ACTIVE', '2'],
            ['KAN-001', 'Dry Clean', '18.50', 'STANDARD_RATED', 'ACTIVE', '3'],
            ['ABA-001', 'Dry Clean', '25.00', 'STANDARD_RATED', 'ACTIVE', '4'],
            ['SUT-001', 'Dry Clean', '30.00', 'STANDARD_RATED', 'ACTIVE', '5'],
            ['LIN-001', 'Wash & Iron', '15.00', 'ZERO_RATED', 'ACTIVE', '6'],
            ['CUR-001', 'Steam Press', '20.00', 'STANDARD_RATED', 'INACTIVE', '7'],
        ];
    }

    public function instructionLines(): array
    {
        return [
            __('gh.import.sheet.offerings_intro'),
            __('gh.import.sheet.offering_refs'),
            $this->livePairs() === []
                ? __('gh.import.sheet.examples_fallback_offerings')
                : __('gh.import.sheet.examples_live'),
            __('gh.import.sheet.required', ['columns' => 'product_code, treatment_name_en, standard_price']),
            __('gh.import.sheet.duplicates', ['key' => 'product_code + treatment_name_en']),
            __('gh.import.sheet.tax_values', ['values' => implode(', ', GhTaxClass::ALL)]),
            __('gh.import.sheet.price_rules'),
            __('gh.import.sheet.max_rows', ['max' => 500]),
        ];
    }

    public function referenceRows(): array
    {
        $rows = [['product_code', 'product_name_en', 'treatment_name_en', 'tax_class']];
        $products = GhProduct::query()->orderBy('code')->limit(200)->get(['code', 'name_en']);
        $treatments = GhTreatment::query()->orderBy('sort_order')->orderBy('name_en')->limit(200)->get(['name_en']);
        $taxClasses = GhTaxClass::ALL;
        $count = max($products->count(), $treatments->count(), count($taxClasses));
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                $products[$i]->code ?? '',
                $products[$i]->name_en ?? '',
                $treatments[$i]->name_en ?? '',
                $taxClasses[$i] ?? '',
            ];
        }

        return $rows;
    }

    protected function parseRow(array $data, array $context): array
    {
        $code = strtoupper($this->text($data, 'product_code', 40, true));
        $treatmentName = $this->text($data, 'treatment_name_en', 120, true);

        $productId = GhProduct::query()->where('code', $code)->value('id');
        if ($productId === null) {
            throw new GhImportRowException(__('gh.import.errors.product_not_found', ['code' => $code]));
        }
        $treatmentId = GhTreatment::query()->whereRaw('LOWER(name_en) = ?', [$this->lower($treatmentName)])->value('id');
        if ($treatmentId === null) {
            throw new GhImportRowException(__('gh.import.errors.treatment_not_found', ['name' => $treatmentName]));
        }

        return [
            'product_code' => $code,
            'treatment_name_en' => $treatmentName,
            'product_id' => (int) $productId,
            'treatment_id' => (int) $treatmentId,
            'standard_price' => $this->money($data, 'standard_price', true),
            'tax_class' => $this->enum($data, 'tax_class', GhTaxClass::ALL, GhTaxClass::STANDARD_RATED),
            'status' => $this->status($data),
            'sort_order' => $this->sortOrder($data),
        ];
    }

    protected function duplicateKey(array $payload): string
    {
        return $payload['product_code'].' / '.$this->lower($payload['treatment_name_en']);
    }

    protected function existsInDatabase(array $payload): bool
    {
        return GhProductOffering::query()
            ->where('product_id', $payload['product_id'])
            ->where('treatment_id', $payload['treatment_id'])
            ->exists();
    }

    protected function persist(array $payload, array $context, int $row, array &$imageErrors): void
    {
        $this->offerings->create($payload + ['currency' => 'AED']);
    }
}
