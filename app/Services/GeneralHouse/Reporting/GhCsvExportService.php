<?php

namespace App\Services\GeneralHouse\Reporting;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * UTF-8 CSV export with formula-injection protection (shared-hosting safe).
 */
class GhCsvExportService
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    public function download(string $basename, array $headers, iterable $rows): StreamedResponse
    {
        $safe = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $basename) ?: 'gh_report';
        $filename = $safe.'_'.gmdate('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            // UTF-8 BOM for Excel Arabic compatibility.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                $safeRow = [];
                foreach ($row as $cell) {
                    $safeRow[] = $this->sanitizeCell($cell);
                }
                fputcsv($out, $safeRow);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function sanitizeCell(string|int|float|null $value): string
    {
        if ($value === null) {
            return '';
        }
        $s = is_string($value) ? $value : (string) $value;
        $s = str_replace(["\r\n", "\r", "\n"], ' ', $s);
        if ($s !== '' && preg_match('/^[=+\-@\t\r]/', $s) === 1) {
            return "'".$s;
        }

        return $s;
    }
}
