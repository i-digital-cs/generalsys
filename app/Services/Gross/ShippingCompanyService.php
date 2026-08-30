<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShipmentAllocation;
use App\Models\Gross\GrossShippingCompany;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ShippingCompanyService
{
    private const IMPORT_MAX_ROWS = 500;

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $q = GrossShippingCompany::query()->orderBy('name');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('address', 'like', $like);
            });
        }

        return $q->paginate(min($perPage, 50))->withQueryString();
    }

    /** @return Collection<int, GrossShippingCompany> */
    public function activeList()
    {
        return GrossShippingCompany::query()
            ->where('status', GrossShippingCompany::STATUS_ACTIVE)
            ->orderBy('name')
            ->get();
    }

    /**
     * Profile stats for the company show page (SQL aggregates only).
     *
     * @return array{
     *     invoice_counts: array<string, int>,
     *     invoice_total: int,
     *     pieces: array{shipped: int, delivered: int, returned: int, with_courier: int, lost: int},
     *     fees_by_currency: list<array{currency: string, fee_status: string, total: string, count: int}>
     * }
     */
    public function stats(GrossShippingCompany $company): array
    {
        $invoiceCounts = GrossSalesInvoice::query()
            ->where('shipping_company_id', $company->id)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->map(fn ($v) => (int) $v)
            ->all();

        $piecesRow = GrossShipmentAllocation::query()
            ->where('shipping_company_id', $company->id)
            ->selectRaw('
                COALESCE(SUM(qty_shipped_pieces), 0) as shipped,
                COALESCE(SUM(qty_delivered_pieces), 0) as delivered,
                COALESCE(SUM(qty_returned_pieces), 0) as returned,
                COALESCE(SUM(qty_with_courier_pieces), 0) as with_courier,
                COALESCE(SUM(qty_lost_pieces), 0) as lost
            ')
            ->first();

        $fees = GrossSalesInvoice::query()
            ->where('shipping_company_id', $company->id)
            ->selectRaw('currency, shipping_fee_status as fee_status, COALESCE(SUM(shipping_fee), 0) as total, COUNT(*) as c')
            ->groupBy('currency', 'shipping_fee_status')
            ->orderBy('currency')
            ->orderBy('shipping_fee_status')
            ->get()
            ->map(fn ($row) => [
                'currency' => (string) $row->currency,
                'fee_status' => (string) $row->fee_status,
                'total' => number_format((float) $row->total, 2, '.', ''),
                'count' => (int) $row->c,
            ])
            ->values()
            ->all();

        return [
            'invoice_counts' => $invoiceCounts,
            'invoice_total' => array_sum($invoiceCounts),
            'pieces' => [
                'shipped' => (int) ($piecesRow->shipped ?? 0),
                'delivered' => (int) ($piecesRow->delivered ?? 0),
                'returned' => (int) ($piecesRow->returned ?? 0),
                'with_courier' => (int) ($piecesRow->with_courier ?? 0),
                'lost' => (int) ($piecesRow->lost ?? 0),
            ],
            'fees_by_currency' => $fees,
        ];
    }

    public function paginateInvoices(GrossShippingCompany $company, int $perPage = 25): LengthAwarePaginator
    {
        return GrossSalesInvoice::query()
            ->where('shipping_company_id', $company->id)
            ->orderByDesc('id')
            ->paginate(min($perPage, 50));
    }

    /**
     * Distinct company names for autocomplete.
     *
     * @return list<string>
     */
    public function nameSuggestions(string $query = '', int $limit = 100): array
    {
        $q = GrossShippingCompany::query()
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
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): GrossShippingCompany
    {
        return GrossShippingCompany::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? GrossShippingCompany::STATUS_ACTIVE,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(GrossShippingCompany $company, array $data): GrossShippingCompany
    {
        $company->update([
            'name' => $data['name'] ?? $company->name,
            'phone' => array_key_exists('phone', $data) ? $data['phone'] : $company->phone,
            'address' => array_key_exists('address', $data) ? $data['address'] : $company->address,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $company->notes,
            'status' => $data['status'] ?? $company->status,
        ]);

        return $company->refresh();
    }

    public function delete(GrossShippingCompany $company): void
    {
        $inUse = GrossSalesInvoice::query()
            ->where('shipping_company_id', $company->id)
            ->exists()
            || $company->allocations()->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'shipping' => [__('gross.errors.shipping_in_use')],
            ]);
        }

        $company->delete();
    }

    public function writeTemplate(string $absolutePath): void
    {
        $sheet = new Spreadsheet;
        $active = $sheet->getActiveSheet();
        $active->setTitle('shipping');
        $active->fromArray([
            ['name', 'phone', 'address', 'notes', 'status'],
            ['Bosta', '01001234567', 'Cairo', 'Main courier', 'ACTIVE'],
            ['Aramex', '01119876543', '', '', 'ACTIVE'],
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
     * @param  array{name: string, phone: string, address: string, notes: string, status: string}  $row
     */
    private function importOneRow(array $row, int $line): string
    {
        $name = trim($row['name']);
        $phone = trim($row['phone']);
        $address = trim($row['address']);
        $notes = trim($row['notes']);
        $status = mb_strtoupper(trim($row['status']));

        if ($name === '' && $phone === '' && $address === '' && $notes === '' && $status === '') {
            return '';
        }

        if ($name === '' || mb_strlen($name) > 191) {
            return __('gross.errors.shipping_import_name', ['row' => $line]);
        }

        if ($phone !== '' && mb_strlen($phone) > 40) {
            return __('gross.errors.shipping_import_phone', ['row' => $line]);
        }

        if ($status === '') {
            $status = GrossShippingCompany::STATUS_ACTIVE;
        }
        if (! in_array($status, [GrossShippingCompany::STATUS_ACTIVE, GrossShippingCompany::STATUS_INACTIVE], true)) {
            return __('gross.errors.shipping_import_status', ['row' => $line]);
        }

        if (GrossShippingCompany::query()->where('name', $name)->exists()) {
            return 'skipped';
        }

        $this->create([
            'name' => $name,
            'phone' => $phone !== '' ? $phone : null,
            'address' => $address !== '' ? $address : null,
            'notes' => $notes !== '' ? $notes : null,
            'status' => $status,
        ]);

        return 'created';
    }

    /**
     * @return list<array{name: string, phone: string, address: string, notes: string, status: string}>
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
        $required = ['name', 'phone', 'address', 'notes', 'status'];
        $indexes = [];
        foreach ($required as $key) {
            $idx = array_search($key, $header, true);
            if ($idx === false) {
                throw ValidationException::withMessages([
                    'file' => [__('gross.errors.shipping_import_headers')],
                ]);
            }
            $indexes[$key] = $idx;
        }

        if (count($matrix) > self::IMPORT_MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => [__('gross.errors.shipping_import_max', ['max' => self::IMPORT_MAX_ROWS])],
            ]);
        }

        $out = [];
        foreach ($matrix as $row) {
            $out[] = [
                'name' => (string) ($row[$indexes['name']] ?? ''),
                'phone' => (string) ($row[$indexes['phone']] ?? ''),
                'address' => (string) ($row[$indexes['address']] ?? ''),
                'notes' => (string) ($row[$indexes['notes']] ?? ''),
                'status' => (string) ($row[$indexes['status']] ?? ''),
            ];
        }

        return $out;
    }

    private function normalizeHeader(string $raw): string
    {
        return mb_strtolower(trim($raw));
    }
}
