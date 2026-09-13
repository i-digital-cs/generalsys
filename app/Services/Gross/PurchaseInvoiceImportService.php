<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossPurchaseInvoiceLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Import purchase invoice drafts from Excel (one row = one line; group by invoice_group).
 */
class PurchaseInvoiceImportService
{
    private const IMPORT_MAX_ROWS = 500;

    public function __construct(private readonly PurchaseInvoiceService $invoices) {}

    public function writeTemplate(string $absolutePath): void
    {
        $sheet = new Spreadsheet;
        $active = $sheet->getActiveSheet();
        $active->setTitle('purchases');
        $active->fromArray([
            [
                'invoice_group',
                'supplier_name',
                'supplier_phone',
                'supplier_address',
                'currency',
                'discount_amount',
                'discount_percent',
                'item_code',
                'qty',
                'buy_price',
                'sale_price',
                'line_discount_amount',
                'line_discount_percent',
                'notes',
            ],
            [
                'PO-A',
                'Cairo Textiles',
                '01001234567',
                'Cairo',
                'EGP',
                '0',
                '0',
                'ITM-00001',
                '10',
                '40',
                '80',
                '0',
                '0',
                'Sample draft',
            ],
            [
                'PO-A',
                'Cairo Textiles',
                '01001234567',
                'Cairo',
                'EGP',
                '0',
                '0',
                'ITM-00002',
                '5',
                '20',
                '45',
                '0',
                '0',
                '',
            ],
        ]);

        (new Xlsx($sheet))->save($absolutePath);
        $sheet->disconnectWorksheets();
    }

    /**
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public function importFromPath(string $absolutePath, User $actor): array
    {
        $rows = $this->readImportRows($absolutePath);
        $groups = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            if ($this->rowIsBlank($row)) {
                continue;
            }

            $group = trim($row['invoice_group']);
            if ($group === '' || mb_strlen($group) > 64) {
                $errors[] = __('gross.errors.purchase_import_group', ['row' => $line]);

                continue;
            }

            $parsed = $this->parseLineRow($row, $line);
            if (is_string($parsed)) {
                $errors[] = $parsed;

                continue;
            }

            $groups[$group]['header'] = $this->mergeHeader($groups[$group]['header'] ?? [], $parsed['header']);
            $groups[$group]['lines'][] = $parsed['line'];
            $groups[$group]['row'] = $groups[$group]['row'] ?? $line;
        }

        $created = 0;
        $skipped = 0;

        foreach ($groups as $group => $payload) {
            $header = $payload['header'];
            $lines = $payload['lines'];
            $row = $payload['row'];

            if ($lines === []) {
                $skipped++;

                continue;
            }

            $headerError = $this->validateHeader($header, $row);
            if ($headerError !== null) {
                $errors[] = $headerError;

                continue;
            }

            try {
                DB::transaction(function () use ($actor, $header, $lines): void {
                    $this->invoices->createDraft($actor, $header, $lines);
                });
                $created++;
            } catch (\Throwable $e) {
                $errors[] = __('gross.errors.purchase_import_failed', [
                    'group' => $group,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return compact('created', 'skipped', 'errors');
    }

    /**
     * @param  array<string, string>  $row
     * @return array{header: array<string, mixed>, line: array<string, mixed>}|string
     */
    private function parseLineRow(array $row, int $line): array|string
    {
        $itemCode = trim($row['item_code']);
        if ($itemCode === '') {
            return __('gross.errors.purchase_import_item', ['row' => $line]);
        }

        $item = GrossItem::query()->where('code', $itemCode)->first();
        if ($item === null) {
            return __('gross.errors.purchase_import_item_missing', ['row' => $line, 'code' => $itemCode]);
        }

        $qty = (int) $row['qty'];
        if ($qty < 1) {
            return __('gross.errors.purchase_import_qty', ['row' => $line]);
        }

        $buyPrice = $this->toFloat($row['buy_price']);
        if ($buyPrice === null || $buyPrice < 0) {
            return __('gross.errors.purchase_import_buy', ['row' => $line]);
        }

        $salePrice = $this->toFloat($row['sale_price']);
        if ($salePrice === null || $salePrice < 0) {
            return __('gross.errors.purchase_import_sale', ['row' => $line]);
        }

        $lineDiscountAmount = $this->toFloat($row['line_discount_amount']) ?? 0.0;
        $lineDiscountPercent = $this->toFloat($row['line_discount_percent']) ?? 0.0;
        if ($lineDiscountAmount < 0 || $lineDiscountPercent < 0 || $lineDiscountPercent > 100) {
            return __('gross.errors.purchase_import_line_discount', ['row' => $line]);
        }

        $discountAmount = $this->toFloat($row['discount_amount']) ?? 0.0;
        $discountPercent = $this->toFloat($row['discount_percent']) ?? 0.0;

        $currency = strtoupper(trim($row['currency'] ?: 'EGP'));
        if (! in_array($currency, ['EGP', 'AED'], true)) {
            return __('gross.errors.purchase_import_currency', ['row' => $line]);
        }

        $usePercent = $discountPercent > 0 && $discountAmount <= 0;
        $lineUsePercent = $lineDiscountPercent > 0 && $lineDiscountAmount <= 0;

        return [
            'header' => [
                'supplier_name' => trim($row['supplier_name']),
                'supplier_phone' => trim($row['supplier_phone']),
                'supplier_address' => trim($row['supplier_address']) !== '' ? trim($row['supplier_address']) : null,
                'currency' => $currency,
                'discount_amount' => $usePercent ? 0 : $discountAmount,
                'discount_percent' => $usePercent ? $discountPercent : 0,
                'notes' => trim($row['notes']) !== '' ? trim($row['notes']) : null,
            ],
            'line' => [
                'item_id' => $item->id,
                'qty' => $qty,
                'unit' => GrossPurchaseInvoiceLine::UNIT_PIECE,
                'buy_price' => $buyPrice,
                'sale_price' => $salePrice,
                'discount_amount' => $lineUsePercent ? 0 : $lineDiscountAmount,
                'discount_percent' => $lineUsePercent ? $lineDiscountPercent : 0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeHeader(array $current, array $incoming): array
    {
        if ($current === []) {
            return $incoming;
        }

        foreach ($incoming as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (! isset($current[$key]) || $current[$key] === null || $current[$key] === '' || $current[$key] === 0 || $current[$key] === 0.0) {
                $current[$key] = $value;
            }
        }

        return $current;
    }

    /**
     * @param  array<string, mixed>  $header
     */
    private function validateHeader(array $header, int $row): ?string
    {
        $name = trim((string) ($header['supplier_name'] ?? ''));
        $phone = trim((string) ($header['supplier_phone'] ?? ''));
        if ($name === '' || mb_strlen($name) > 191) {
            return __('gross.errors.purchase_import_supplier', ['row' => $row]);
        }
        if ($phone === '' || mb_strlen($phone) > 40) {
            return __('gross.errors.purchase_import_supplier', ['row' => $row]);
        }

        return null;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function rowIsBlank(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function toFloat(string $raw): ?float
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 0.0;
        }
        if (! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    /**
     * @return list<array<string, string>>
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
        $required = [
            'invoice_group',
            'supplier_name',
            'supplier_phone',
            'supplier_address',
            'currency',
            'discount_amount',
            'discount_percent',
            'item_code',
            'qty',
            'buy_price',
            'sale_price',
            'line_discount_amount',
            'line_discount_percent',
            'notes',
        ];
        $indexes = [];
        foreach ($required as $key) {
            $idx = array_search($key, $header, true);
            if ($idx === false) {
                throw ValidationException::withMessages([
                    'file' => [__('gross.errors.purchase_import_headers')],
                ]);
            }
            $indexes[$key] = $idx;
        }

        if (count($matrix) > self::IMPORT_MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => [__('gross.errors.purchase_import_max', ['max' => self::IMPORT_MAX_ROWS])],
            ]);
        }

        $out = [];
        foreach ($matrix as $row) {
            $mapped = [];
            foreach ($indexes as $key => $idx) {
                $mapped[$key] = (string) ($row[$idx] ?? '');
            }
            $out[] = $mapped;
        }

        return $out;
    }
}
