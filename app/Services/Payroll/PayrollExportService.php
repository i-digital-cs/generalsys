<?php

namespace App\Services\Payroll;

use App\Models\PayrollRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Payslip PDF + monthly payroll Excel (T-5.2). Hostinger-safe: Dompdf + PhpSpreadsheet.
 */
class PayrollExportService
{
    public function downloadPayslipPdf(PayrollRecord $payroll): \Barryvdh\DomPDF\PDF
    {
        $payroll->loadMissing(['user', 'adjustments']);

        return Pdf::loadView('exports.payslip-pdf', [
            'payroll' => $payroll,
        ])->setPaper('a4');
    }

    /**
     * Build XLSX for a month (chunked query ≤ 100 rows per page in memory).
     *
     * @return string Absolute path under storage/app
     */
    public function buildMonthlyExcel(int $month, int $year): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(sprintf('%04d-%02d', $year, $month));

        $headers = [
            'Employee', 'Code/Email', 'Currency', 'Status', 'Base', 'Gross', 'Deductions', 'Net',
            'FT days', 'Online ½', 'Online full', 'Late min',
        ];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.'1', $header);
            $col++;
        }

        $row = 2;
        PayrollRecord::query()
            ->with('user')
            ->where('month', $month)
            ->where('year', $year)
            ->orderBy('id')
            ->chunkById(100, function (Collection $records) use ($sheet, &$row) {
                foreach ($records as $payroll) {
                    /** @var PayrollRecord $payroll */
                    $user = $payroll->user;
                    $name = $user?->name_en ?? $user?->name_ar ?? '';
                    $sheet->setCellValue('A'.$row, $name);
                    $sheet->setCellValue('B'.$row, $user?->email ?? '');
                    $sheet->setCellValue('C'.$row, $payroll->currency);
                    $sheet->setCellValue('D'.$row, $payroll->status);
                    $sheet->setCellValue('E'.$row, (float) $payroll->base_salary);
                    $sheet->setCellValue('F'.$row, (float) $payroll->gross_pay);
                    $sheet->setCellValue('G'.$row, (float) $payroll->total_deductions);
                    $sheet->setCellValue('H'.$row, (float) $payroll->net_pay);
                    $sheet->setCellValue('I'.$row, (int) $payroll->full_time_days);
                    $sheet->setCellValue('J'.$row, (int) $payroll->online_half_days);
                    $sheet->setCellValue('K'.$row, (int) $payroll->online_full_days);
                    $sheet->setCellValue('L'.$row, (int) $payroll->late_minutes_total);
                    $row++;
                }
            });

        $relative = sprintf('exports/payroll-%04d-%02d-%s.xlsx', $year, $month, uniqid());
        Storage::disk('local')->makeDirectory('exports');
        $absolute = Storage::disk('local')->path($relative);

        (new Xlsx($spreadsheet))->save($absolute);
        $spreadsheet->disconnectWorksheets();

        return $relative;
    }

    public function streamStoredExcel(string $relativePath, string $downloadName): StreamedResponse
    {
        return Storage::disk('local')->download($relativePath, $downloadName);
    }
}
