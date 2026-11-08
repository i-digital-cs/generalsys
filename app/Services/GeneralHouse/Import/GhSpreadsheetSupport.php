<?php

namespace App\Services\GeneralHouse\Import;

use App\Services\GeneralHouse\Import\Importers\GhEntityImporter;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GhSpreadsheetSupport
{
    public const MAX_ROWS = 500;

    public const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * @param  list<string>  $requiredHeaders
     * @param  list<string>  $knownHeaders
     * @param  list<string>  $dateHeaders
     * @return list<array{line: int, data: array<string, string>}>
     */
    public function readRows(string $absolutePath, array $requiredHeaders, array $knownHeaders, array $dateHeaders = []): array
    {
        try {
            $spreadsheet = IOFactory::load($absolutePath);
        } catch (\Throwable) {
            throw $this->fileError(__('gh.import.errors.unreadable_file'));
        }

        $matrix = $spreadsheet->getSheet(0)->toArray(null, true, false, false);
        $spreadsheet->disconnectWorksheets();

        if ($matrix === []) {
            throw $this->fileError(__('gh.import.errors.empty_file'));
        }

        $indexes = $this->headerIndexes(array_shift($matrix), $requiredHeaders, $knownHeaders);
        $this->assertWithinLimit($matrix);

        $rows = [];
        foreach ($matrix as $offset => $cells) {
            $data = [];
            foreach ($indexes as $name => $index) {
                $data[$name] = $this->cellToString($cells[$index] ?? null, in_array($name, $dateHeaders, true));
            }
            if (array_filter($data, fn (string $v) => $v !== '') !== []) {
                $rows[] = ['line' => $offset + 2, 'data' => $data];
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @param  list<string>  $requiredHeaders
     * @param  list<string>  $knownHeaders
     * @return array<string, int>
     */
    private function headerIndexes(array $headerRow, array $requiredHeaders, array $knownHeaders): array
    {
        $found = [];
        foreach ($headerRow as $index => $label) {
            $name = $this->normalizeHeader((string) $label);
            if ($name !== '' && in_array($name, $knownHeaders, true) && ! isset($found[$name])) {
                $found[$name] = $index;
            }
        }

        $missing = array_values(array_diff($requiredHeaders, array_keys($found)));
        if ($missing !== []) {
            throw $this->fileError(__('gh.import.errors.missing_columns', ['columns' => implode(', ', $missing)]));
        }

        return $found;
    }

    /** @param  array<int, mixed>  $matrix */
    private function assertWithinLimit(array $matrix): void
    {
        $filled = array_filter($matrix, fn ($cells) => is_array($cells) && array_filter($cells, fn ($v) => $v !== null && trim((string) $v) !== '') !== []);
        if (count($filled) > self::MAX_ROWS) {
            throw $this->fileError(__('gh.import.errors.too_many_rows', ['max' => self::MAX_ROWS]));
        }
    }

    public function normalizeHeader(string $label): string
    {
        $label = preg_replace('/^\xEF\xBB\xBF/', '', $label) ?? $label;
        $label = mb_strtolower(trim($label));

        return trim((string) preg_replace('/[\s\-]+/', '_', $label), '_');
    }

    private function cellToString(mixed $value, bool $isDate): string
    {
        if ($value === null) {
            return '';
        }
        if ($isDate && is_numeric($value)) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return (string) (int) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    public function writeTemplate(string $absolutePath, GhEntityImporter $importer): void
    {
        $book = new Spreadsheet;
        $data = $book->getActiveSheet();
        $data->setTitle('Import Data');
        $data->fromArray(array_merge([$importer->headers()], $importer->exampleRows()), null, 'A1', true);
        $this->styleHeaderRow($data, count($importer->headers()));

        $instructions = $book->createSheet();
        $instructions->setTitle('Instructions');
        $instructions->fromArray(array_map(fn (string $line) => [$line], $importer->instructionLines()), null, 'A1', true);
        $instructions->getColumnDimension('A')->setWidth(120);

        $reference = $book->createSheet();
        $reference->setTitle('Reference Values');
        $reference->fromArray($importer->referenceRows(), null, 'A1', true);
        $this->styleHeaderRow($reference, max(1, count($importer->referenceRows()[0] ?? [])));

        $book->setActiveSheetIndex(0);
        (new Xlsx($book))->save($absolutePath);
        $book->disconnectWorksheets();
    }

    /** @param  list<array{row: int, message: string}>  $errors */
    public function writeErrors(string $absolutePath, array $errors): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Errors');
        $rows = [[__('gh.import.preview.row'), __('gh.import.preview.message')]];
        foreach ($errors as $error) {
            $rows[] = [$error['row'], $this->safeCell($error['message'])];
        }
        $sheet->fromArray($rows, null, 'A1', true);
        $this->styleHeaderRow($sheet, 2);
        (new Xlsx($book))->save($absolutePath);
        $book->disconnectWorksheets();
    }

    public function downloadTemplate(GhEntityImporter $importer): BinaryFileResponse
    {
        $path = $this->tempPath();
        $this->writeTemplate($path, $importer);

        return $this->download($path, $importer->templateFilename());
    }

    /** @param  list<array{row: int, message: string}>  $errors */
    public function downloadErrors(string $entity, array $errors): BinaryFileResponse
    {
        $path = $this->tempPath();
        $this->writeErrors($path, $errors);

        return $this->download($path, 'general-house-'.$entity.'-import-errors.xlsx');
    }

    private function download(string $path, string $filename): BinaryFileResponse
    {
        return response()
            ->download($path, $filename, ['Content-Type' => self::XLSX_MIME])
            ->deleteFileAfterSend(true);
    }

    private function tempPath(): string
    {
        $dir = storage_path('app/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir.'/gh-import-'.bin2hex(random_bytes(8)).'.xlsx';
    }

    private function styleHeaderRow(Worksheet $sheet, int $columns): void
    {
        $last = Coordinate::stringFromColumnIndex($columns);
        $range = 'A1:'.$last.'1';
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E7F5FE');
        for ($i = 1; $i <= $columns; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
    }

    /** Neutralise spreadsheet formula injection for user-supplied text. */
    private function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    private function fileError(string $message): ValidationException
    {
        return ValidationException::withMessages(['file' => [$message]])->errorBag('ghImport');
    }
}
