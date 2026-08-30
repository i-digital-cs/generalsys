<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossSupplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SupplierService
{
    private const IMPORT_MAX_ROWS = 500;

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $q = GrossSupplier::query()->orderBy('name');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('address', 'like', $like)
                    ->orWhere('notes', 'like', $like);
            });
        }

        return $q->paginate(min($perPage, 50))->withQueryString();
    }

    /**
     * Suppliers for purchase forms (bounded list, not paginated).
     *
     * @return Collection<int, GrossSupplier>
     */
    public function listForForms(int $limit = 100): Collection
    {
        return GrossSupplier::query()
            ->orderBy('name')
            ->limit(min($limit, 200))
            ->get();
    }

    /**
     * Distinct supplier names for autocomplete.
     *
     * @return list<string>
     */
    public function nameSuggestions(int $limit = 100): array
    {
        return GrossSupplier::query()
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->select('name')
            ->distinct()
            ->orderBy('name')
            ->limit(min($limit, 100))
            ->pluck('name')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();
    }

    /**
     * @param  array{name: string, phone: string, address?: string|null, notes?: string|null}  $data
     */
    public function create(array $data): GrossSupplier
    {
        return GrossSupplier::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * @param  array{name: string, phone: string, address?: string|null, notes?: string|null}  $data
     */
    public function update(GrossSupplier $supplier, array $data): GrossSupplier
    {
        $supplier->update([
            'name' => $data['name'] ?? $supplier->name,
            'phone' => $data['phone'] ?? $supplier->phone,
            'address' => array_key_exists('address', $data) ? $data['address'] : $supplier->address,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $supplier->notes,
        ]);

        return $supplier->refresh();
    }

    public function delete(GrossSupplier $supplier): void
    {
        $supplier->delete();
    }

    public function writeTemplate(string $absolutePath): void
    {
        $sheet = new Spreadsheet;
        $active = $sheet->getActiveSheet();
        $active->setTitle('suppliers');
        $active->fromArray([
            ['name', 'phone', 'address', 'notes'],
            ['Cairo Textiles', '01001234567', 'Cairo', 'Main supplier'],
            ['Alex Fabrics', '01119876543', '', ''],
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
     * @param  array{name: string, phone: string, address: string, notes: string}  $row
     */
    private function importOneRow(array $row, int $line): string
    {
        $name = trim($row['name']);
        $phone = trim($row['phone']);
        $address = trim($row['address']);
        $notes = trim($row['notes']);

        if ($name === '' && $phone === '' && $address === '' && $notes === '') {
            return '';
        }

        if ($name === '' || mb_strlen($name) > 191) {
            return __('gross.errors.supplier_import_name', ['row' => $line]);
        }

        if ($phone === '' || mb_strlen($phone) > 40) {
            return __('gross.errors.supplier_import_phone', ['row' => $line]);
        }

        if (GrossSupplier::query()->where('phone', $phone)->exists()) {
            return 'skipped';
        }

        $this->create([
            'name' => $name,
            'phone' => $phone,
            'address' => $address !== '' ? $address : null,
            'notes' => $notes !== '' ? $notes : null,
        ]);

        return 'created';
    }

    /**
     * @return list<array{name: string, phone: string, address: string, notes: string}>
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

        $header = array_map(fn ($v) => mb_strtolower(trim((string) $v)), array_shift($matrix));
        $required = ['name', 'phone', 'address', 'notes'];
        $indexes = [];
        foreach ($required as $key) {
            $idx = array_search($key, $header, true);
            if ($idx === false) {
                throw ValidationException::withMessages([
                    'file' => [__('gross.errors.supplier_import_headers')],
                ]);
            }
            $indexes[$key] = $idx;
        }

        if (count($matrix) > self::IMPORT_MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => [__('gross.errors.supplier_import_max', ['max' => self::IMPORT_MAX_ROWS])],
            ]);
        }

        $out = [];
        foreach ($matrix as $row) {
            $out[] = [
                'name' => (string) ($row[$indexes['name']] ?? ''),
                'phone' => (string) ($row[$indexes['phone']] ?? ''),
                'address' => (string) ($row[$indexes['address']] ?? ''),
                'notes' => (string) ($row[$indexes['notes']] ?? ''),
            ];
        }

        return $out;
    }
}
