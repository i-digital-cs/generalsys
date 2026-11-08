<?php

namespace App\Services\GeneralHouse\Import\Importers;

use App\Models\GeneralHouse\GhB2bContract;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhTreatment;
use App\Services\GeneralHouse\Customers\GhContractService;
use App\Services\GeneralHouse\Import\GhImportCommitResult;
use App\Services\GeneralHouse\Import\GhImportPreviewResult;
use App\Services\GeneralHouse\Import\GhImportRowException;
use App\Support\GeneralHouse\GhCatalogStatus;
use App\Support\GeneralHouse\GhContractStatus;
use App\Support\GeneralHouse\GhCustomerType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Multi-row import: rows are grouped into one contract each (explicit `reference`, else `import_group`,
 * else consecutive rows with the same customer + dates). A blank reference is auto-allocated (GH-CTR-######).
 * A contract is created atomically — any invalid row rejects the whole contract.
 */
class ContractImporter implements GhEntityImporter
{
    use ParsesImportCells;

    private const HEADER_FIELDS = ['customer_phone', 'customer_email', 'effective_from', 'effective_to', 'status', 'notes'];

    private const EXAMPLE_GROUP_SIZES = [3, 2, 1];

    public function __construct(private readonly GhContractService $contracts) {}

    public function entityKey(): string
    {
        return 'contracts';
    }

    public function modelClass(): string
    {
        return GhB2bContract::class;
    }

    public function templateFilename(): string
    {
        return 'general-house-b2b-contracts-example.xlsx';
    }

    public function headers(): array
    {
        return [
            'reference', 'import_group', 'customer_phone', 'customer_email', 'effective_from', 'effective_to', 'status', 'notes',
            'product_code', 'treatment_name_en', 'contract_price', 'offering_status',
        ];
    }

    public function requiredHeaders(): array
    {
        return ['customer_phone', 'customer_email', 'effective_from', 'product_code', 'treatment_name_en', 'contract_price'];
    }

    public function dateHeaders(): array
    {
        return ['effective_from', 'effective_to'];
    }

    public function exampleRows(): array
    {
        $live = $this->liveExampleRows();

        return $live !== [] ? $live : $this->fallbackExampleRows();
    }

    /** @return list<list<string>> */
    private function liveExampleRows(): array
    {
        $customers = GhCustomer::query()->where('type', GhCustomerType::B2B)->orderBy('id')->limit(3)->get(['phone', 'email']);
        $offerings = GhProductOffering::query()
            ->with(['product:id,code', 'treatment:id,name_en'])
            ->where('status', GhCatalogStatus::ACTIVE)
            ->orderBy('id')
            ->limit(6)
            ->get();
        $customers = $customers->filter(fn (GhCustomer $c) => trim((string) $c->phone) !== '' || trim((string) $c->email) !== '')->values();
        if ($customers->isEmpty() || $offerings->isEmpty()) {
            return [];
        }

        $rows = [];
        $cursor = 0;
        foreach (self::EXAMPLE_GROUP_SIZES as $g => $size) {
            $customer = $customers[$g % $customers->count()];
            $year = 2030 + $g;
            for ($k = 0; $k < min($size, $offerings->count()); $k++) {
                $o = $offerings[$cursor++ % $offerings->count()];
                $rows[] = [
                    '', 'G'.($g + 1),
                    trim((string) $customer->phone) !== '' ? (string) $customer->phone : '',
                    trim((string) $customer->phone) !== '' ? '' : (string) $customer->email,
                    $year.'-01-01', $year.'-12-31', 'DRAFT', $k === 0 ? 'Example contract' : '',
                    (string) $o->product?->code, (string) $o->treatment?->name_en,
                    number_format(max(0.0, (float) $o->standard_price * 0.9), 2, '.', ''), 'ACTIVE',
                ];
            }
        }

        return $rows;
    }

    /** @return list<list<string>> */
    private function fallbackExampleRows(): array
    {
        return [
            ['', 'G1', '971044445555', '', '2027-01-01', '2027-12-31', 'DRAFT', 'Annual hotel contract', 'KAN-001', 'Wash & Iron', '10.00', 'ACTIVE'],
            ['', 'G1', '971044445555', '', '2027-01-01', '2027-12-31', 'DRAFT', '', 'KAN-001', 'Iron Only', '5.00', 'ACTIVE'],
            ['', 'G1', '971044445555', '', '2027-01-01', '2027-12-31', 'DRAFT', '', 'LIN-001', 'Wash & Iron', '13.00', 'ACTIVE'],
            ['', 'G2', '', 'accounts@oasis.example', '2027-02-01', '', 'DRAFT', 'Open-ended restaurant contract', 'LIN-001', 'Wash & Iron', '12.50', 'ACTIVE'],
            ['', 'G2', '', 'accounts@oasis.example', '2027-02-01', '', 'DRAFT', '', 'CUR-001', 'Steam Press', '17.00', 'INACTIVE'],
            ['', 'G3', '971044445555', '', '2028-01-01', '2028-06-30', 'DRAFT', 'Follow-up period', 'SUT-001', 'Dry Clean', '27.00', 'ACTIVE'],
        ];
    }

    public function instructionLines(): array
    {
        return [
            __('gh.import.sheet.contracts_intro'),
            __('gh.import.sheet.required', ['columns' => 'customer_phone or customer_email, effective_from, product_code, treatment_name_en, contract_price']),
            __('gh.import.sheet.contract_reference_auto'),
            __('gh.import.sheet.contract_grouping'),
            __('gh.import.sheet.contract_import_first'),
            __('gh.import.sheet.contract_status', ['default' => GhContractStatus::DRAFT]),
            __('gh.import.sheet.contract_dates'),
            __('gh.import.sheet.price_rules'),
            __('gh.import.sheet.max_rows', ['max' => 500]),
        ];
    }

    public function referenceRows(): array
    {
        $rows = [['status', 'offering_status', 'b2b_customer_name_en', 'b2b_customer_phone']];
        $statuses = GhContractStatus::ALL;
        $customers = GhCustomer::query()->where('type', GhCustomerType::B2B)->orderBy('name_ar')->limit(200)->get(['name_en', 'name_ar', 'phone']);
        $count = max(count($statuses), $customers->count(), 2);
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                $statuses[$i] ?? '',
                GhCatalogStatus::ALL[$i] ?? '',
                $customers[$i]->name_en ?? $customers[$i]->name_ar ?? '',
                $customers[$i]->phone ?? '',
            ];
        }

        return $rows;
    }

    public function preview(array $rows, array $context = []): GhImportPreviewResult
    {
        $groups = $this->groupRows($rows);

        $accepted = [];
        $duplicates = [];
        $errors = [];
        $invalid = 0;
        foreach ($groups as $group) {
            $groupRows = $group['rows'];
            $reference = $group['reference'];
            if ($reference !== null && GhB2bContract::query()->where('reference', $reference)->exists()) {
                $duplicates[] = ['row' => $groupRows[0]['line'], 'key' => $reference];

                continue;
            }
            $groupErrors = [];
            $payload = $this->buildContract($reference, $groupRows, $groupErrors);
            if ($groupErrors !== []) {
                $invalid++;
                array_push($errors, ...$groupErrors);

                continue;
            }
            if ($reference === null && $this->sameContractExists($payload)) {
                $duplicates[] = ['row' => $groupRows[0]['line'], 'key' => $this->autoGroupLabel($payload)];

                continue;
            }
            $accepted[] = ['row' => $groupRows[0]['line'], 'payload' => $payload];
        }

        return GhImportPreviewResult::fromOutcome($this->entityKey(), count($groups), count($accepted), $invalid, $errors, $duplicates, $accepted);
    }

    /**
     * Explicit reference wins; else import_group (any position); else consecutive rows sharing customer + dates.
     *
     * @param  list<array{line: int, data: array<string, string>}>  $rows
     * @return list<array{reference: ?string, rows: list<array{line: int, data: array<string, string>}>}>
     */
    private function groupRows(array $rows): array
    {
        $groups = [];
        $index = [];
        $previousSignature = null;
        foreach ($rows as $row) {
            $data = $row['data'];
            $reference = strtoupper(trim($data['reference'] ?? ''));
            $importGroup = trim($data['import_group'] ?? '');
            if ($reference !== '') {
                $key = 'REF:'.$reference;
                $previousSignature = null;
            } elseif ($importGroup !== '') {
                $key = 'GRP:'.mb_strtolower($importGroup);
                $previousSignature = null;
            } else {
                $signature = implode('|', [
                    mb_strtolower(trim($data['customer_phone'] ?? '')),
                    mb_strtolower(trim($data['customer_email'] ?? '')),
                    trim($data['effective_from'] ?? ''),
                    trim($data['effective_to'] ?? ''),
                ]);
                if ($signature !== $previousSignature) {
                    $key = 'AUTO:'.count($groups);
                    $previousSignature = $signature;
                } else {
                    $key = array_key_last($index);
                }
            }
            if (! isset($index[$key])) {
                $groups[] = ['reference' => $reference !== '' ? $reference : null, 'rows' => []];
                $index[$key] = array_key_last($groups);
            }
            $groups[$index[$key]]['rows'][] = $row;
        }

        return $groups;
    }

    /** @param  array<string, mixed>  $payload */
    private function sameContractExists(array $payload): bool
    {
        return GhB2bContract::query()
            ->where('customer_id', $payload['customer_id'])
            ->whereDate('effective_from', $payload['effective_from'])
            ->when(
                $payload['effective_to'] === null,
                fn ($q) => $q->whereNull('effective_to'),
                fn ($q) => $q->whereDate('effective_to', $payload['effective_to'])
            )
            ->exists();
    }

    /** @param  array<string, mixed>  $payload */
    private function autoGroupLabel(array $payload): string
    {
        return $payload['customer_id'].' / '.$payload['effective_from'].' – '.($payload['effective_to'] ?? '∞');
    }

    /**
     * @param  list<array{line: int, data: array<string, string>}>  $groupRows
     * @param  list<array{row: int, message: string}>  $errors
     * @return array<string, mixed>
     */
    private function buildContract(?string $reference, array $groupRows, array &$errors): array
    {
        $first = $groupRows[0]['line'];
        $header = [];
        try {
            $header = $this->parseHeader($reference, $groupRows);
        } catch (GhImportRowException $e) {
            $errors[] = ['row' => $first, 'message' => $e->getMessage()];
        }

        $offerings = [];
        $seen = [];
        foreach ($groupRows as $row) {
            try {
                $line = $this->parseOffering($row['data']);
                if (isset($seen[$line['offering_id']])) {
                    throw new GhImportRowException(__('gh.import.errors.contract_offering_repeated'));
                }
                $seen[$line['offering_id']] = true;
                $offerings[] = $line;
            } catch (GhImportRowException $e) {
                $errors[] = ['row' => $row['line'], 'message' => $e->getMessage()];
            }
        }

        return $header + ['offerings' => $offerings];
    }

    /**
     * @param  list<array{line: int, data: array<string, string>}>  $groupRows
     * @return array<string, mixed>
     */
    private function parseHeader(?string $reference, array $groupRows): array
    {
        $merged = [];
        foreach (self::HEADER_FIELDS as $field) {
            $merged[$field] = '';
            foreach ($groupRows as $row) {
                if (trim($row['data'][$field] ?? '') !== '') {
                    $merged[$field] = trim($row['data'][$field]);

                    break;
                }
            }
        }

        if ($reference !== null && mb_strlen($reference) > 60) {
            throw new GhImportRowException(__('gh.import.errors.too_long', ['field' => 'reference', 'max' => 60]));
        }
        $from = $this->date($merged['effective_from'], 'effective_from', true);
        $to = $this->date($merged['effective_to'], 'effective_to', false);
        if ($to !== null && $to < $from) {
            throw new GhImportRowException(__('gh.errors.contract_end_before_start'));
        }

        return [
            'reference' => $reference,
            'customer_id' => $this->customerId($merged['customer_phone'], $merged['customer_email']),
            'effective_from' => $from,
            'effective_to' => $to,
            'status' => $this->enum($merged, 'status', GhContractStatus::ALL, GhContractStatus::DRAFT),
            'notes' => $merged['notes'] === '' ? null : mb_substr($merged['notes'], 0, 5000),
        ];
    }

    private function date(string $value, string $field, bool $required): ?string
    {
        if ($value === '') {
            if ($required) {
                throw new GhImportRowException(__('gh.import.errors.required', ['field' => $field]));
            }

            return null;
        }
        try {
            $parsed = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            $parsed = false;
        }
        if (! $parsed || $parsed->format('Y-m-d') !== $value) {
            throw new GhImportRowException(__('gh.import.errors.invalid_date', ['field' => $field]));
        }

        return $value;
    }

    private function customerId(string $phone, string $email): int
    {
        if ($phone === '' && $email === '') {
            throw new GhImportRowException(__('gh.import.errors.contract_customer_identity'));
        }
        $query = GhCustomer::query();
        $phone !== '' ? $query->where('phone', $phone) : $query->whereRaw('LOWER(email) = ?', [$this->lower($email)]);
        $customer = $query->first(['id', 'type']);

        if ($customer === null) {
            throw new GhImportRowException(__('gh.import.errors.customer_not_found', ['identity' => $phone !== '' ? $phone : $email]));
        }
        if ($customer->type !== GhCustomerType::B2B) {
            throw new GhImportRowException(__('gh.errors.contract_requires_b2b'));
        }

        return (int) $customer->id;
    }

    /**
     * @param  array<string, string>  $data
     * @return array{offering_id: int, contract_price: string, status: string}
     */
    private function parseOffering(array $data): array
    {
        $code = strtoupper($this->text($data, 'product_code', 40, true));
        $treatment = $this->text($data, 'treatment_name_en', 120, true);
        $offeringId = GhProductOffering::query()
            ->whereIn('product_id', GhProduct::query()->where('code', $code)->select('id'))
            ->whereIn('treatment_id', GhTreatment::query()->whereRaw('LOWER(name_en) = ?', [$this->lower($treatment)])->select('id'))
            ->value('id');
        if ($offeringId === null) {
            throw new GhImportRowException(__('gh.import.errors.offering_not_found', ['code' => $code, 'treatment' => $treatment]));
        }

        return [
            'offering_id' => (int) $offeringId,
            'contract_price' => $this->money($data, 'contract_price', true),
            'status' => $this->enum($data + ['offering_status' => ''], 'offering_status', GhCatalogStatus::ALL, GhCatalogStatus::ACTIVE),
        ];
    }

    public function commit(GhImportPreviewResult $preview, array $context = []): GhImportCommitResult
    {
        $created = 0;
        $skipped = $preview->duplicate;
        $errors = [];
        foreach ($preview->rows as $item) {
            $payload = $item['payload'];
            if ($payload['reference'] !== null && GhB2bContract::query()->where('reference', $payload['reference'])->exists()) {
                $skipped++;

                continue;
            }
            try {
                DB::transaction(function () use ($payload) {
                    $contract = $this->contracts->create($payload);
                    foreach ($payload['offerings'] as $offering) {
                        $this->contracts->addOffering($contract, $offering);
                    }
                });
                $created++;
            } catch (ValidationException $e) {
                $errors[] = __('gh.import.errors.row_prefix', ['row' => $item['row']]).' '.collect($e->errors())->flatten()->first();
            }
        }

        return new GhImportCommitResult($created, $skipped, 0, $errors);
    }
}
