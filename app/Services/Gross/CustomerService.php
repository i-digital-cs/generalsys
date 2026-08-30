<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossCustomer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CustomerService
{
    private const IMPORT_MAX_ROWS = 500;

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $q = GrossCustomer::query()->orderBy('name');

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

    /**
     * Customers for invoice forms (bounded list, not paginated).
     *
     * @return Collection<int, GrossCustomer>
     */
    public function listForForms(int $limit = 100): Collection
    {
        return GrossCustomer::query()
            ->orderBy('name')
            ->limit(min($limit, 200))
            ->get();
    }

    /**
     * Distinct customer names for autocomplete.
     *
     * @return list<string>
     */
    public function nameSuggestions(string $query = '', int $limit = 100): array
    {
        $q = GrossCustomer::query()
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
     * @return Collection<int, GrossCustomer>
     */
    public function search(string $q, int $limit = 20): Collection
    {
        $q = trim($q);
        if ($q === '') {
            return collect();
        }

        return GrossCustomer::query()
            ->where(function ($query) use ($q): void {
                $query->where('name', 'like', '%'.$q.'%')
                    ->orWhere('phone', 'like', '%'.$q.'%');
            })
            ->orderBy('name')
            ->limit(min($limit, 50))
            ->get();
    }

    /**
     * @param  array{name: string, phone: string, address?: string|null}  $data
     */
    public function create(array $data): GrossCustomer
    {
        return GrossCustomer::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'address' => $data['address'] ?? null,
        ]);
    }

    /**
     * @param  array{name: string, phone: string, address?: string|null}  $data
     */
    public function update(GrossCustomer $customer, array $data): GrossCustomer
    {
        $customer->update([
            'name' => $data['name'] ?? $customer->name,
            'phone' => $data['phone'] ?? $customer->phone,
            'address' => array_key_exists('address', $data) ? $data['address'] : $customer->address,
        ]);

        return $customer->refresh();
    }

    public function delete(GrossCustomer $customer): void
    {
        if ($customer->invoices()->exists()) {
            throw ValidationException::withMessages([
                'customer' => [__('gross.errors.customer_in_use')],
            ]);
        }

        $customer->delete();
    }

    public function writeTemplate(string $absolutePath): void
    {
        $sheet = new Spreadsheet;
        $active = $sheet->getActiveSheet();
        $active->setTitle('customers');
        $active->fromArray([
            ['name', 'phone', 'address'],
            ['Ahmed Ali', '01001234567', 'Cairo'],
            ['Sara Hassan', '01119876543', ''],
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
     * @param  array{name: string, phone: string, address: string}  $row
     */
    private function importOneRow(array $row, int $line): string
    {
        $name = trim($row['name']);
        $phone = trim($row['phone']);
        $address = trim($row['address']);

        if ($name === '' && $phone === '' && $address === '') {
            return '';
        }

        if ($name === '' || mb_strlen($name) > 191) {
            return __('gross.errors.customer_import_name', ['row' => $line]);
        }

        if ($phone === '' || mb_strlen($phone) > 40) {
            return __('gross.errors.customer_import_phone', ['row' => $line]);
        }

        if (GrossCustomer::query()->where('phone', $phone)->exists()) {
            return 'skipped';
        }

        $this->create([
            'name' => $name,
            'phone' => $phone,
            'address' => $address !== '' ? $address : null,
        ]);

        return 'created';
    }

    /**
     * @return list<array{name: string, phone: string, address: string}>
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
        $required = ['name', 'phone', 'address'];
        $indexes = [];
        foreach ($required as $key) {
            $idx = array_search($key, $header, true);
            if ($idx === false) {
                throw ValidationException::withMessages([
                    'file' => [__('gross.errors.customer_import_headers')],
                ]);
            }
            $indexes[$key] = $idx;
        }

        if (count($matrix) > self::IMPORT_MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => [__('gross.errors.customer_import_max', ['max' => self::IMPORT_MAX_ROWS])],
            ]);
        }

        $out = [];
        foreach ($matrix as $row) {
            $out[] = [
                'name' => (string) ($row[$indexes['name']] ?? ''),
                'phone' => (string) ($row[$indexes['phone']] ?? ''),
                'address' => (string) ($row[$indexes['address']] ?? ''),
            ];
        }

        return $out;
    }

    private function normalizeHeader(string $raw): string
    {
        return mb_strtolower(trim($raw));
    }
}
