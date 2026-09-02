<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossSalesInvoice;
use App\Support\DompdfFonts;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesInvoiceExportService
{
    public function __construct(
        private readonly SalesInvoiceDisplayService $display,
    ) {}

    public function downloadPdf(GrossSalesInvoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->loadMissing(['lines.allocation', 'salesUser', 'customer', 'shippingCompany']);
        $display = $this->display->build($invoice);

        $fontDir = DompdfFonts::directory();

        return Pdf::loadView('exports.gross-sales-invoice-pdf', [
            'invoice' => $invoice,
            'display' => $display,
        ])
            ->setPaper('a4', ($display['has_tracking'] ?? false) ? 'landscape' : 'portrait')
            ->setOptions([
                'defaultFont' => 'Tajawal',
                'fontDir' => $fontDir,
                'fontCache' => $fontDir,
                'isRemoteEnabled' => false,
                'chroot' => realpath(base_path()) ?: base_path(),
            ]);
    }

    public function downloadExcel(GrossSalesInvoice $invoice): StreamedResponse
    {
        $invoice->loadMissing(['lines.allocation', 'salesUser']);
        $display = $this->display->build($invoice);
        $tracked = (bool) ($display['has_tracking'] ?? false);

        $sheet = new Spreadsheet;
        $active = $sheet->getActiveSheet();
        $active->setTitle('invoice');

        $headerRows = [
            ['invoice_number', $invoice->invoice_number],
            ['status', $invoice->status],
            ['customer', $invoice->customer_name],
            ['phone', $invoice->customer_phone],
            ['sales_person', $invoice->salesUser?->localizedName() ?? ''],
            ['shipping_company', $invoice->shipping_company_name],
            ['currency', $invoice->currency],
            ['subtotal', (string) $invoice->subtotal],
            ['discount_amount', (string) $invoice->discount_amount],
            ['discount_percent', (string) $invoice->discount_percent],
            ['shipping_fee', (string) $invoice->shipping_fee],
            ['total', (string) $invoice->total],
        ];

        if ($tracked) {
            $headerRows[] = ['returned_merchandise', (string) ($display['returned_merchandise'] ?? 0)];
            $headerRows[] = ['net_subtotal', (string) ($display['net_subtotal'] ?? 0)];
            $headerRows[] = ['net_discount', (string) ($display['net_discount'] ?? 0)];
            $headerRows[] = ['net_total', (string) ($display['net_grand_total'] ?? 0)];
        }

        $headerRows[] = ['notes', (string) ($invoice->notes ?? '')];
        $headerRows[] = [];

        $lineHeaders = $tracked
            ? ['code', 'name', 'size', 'qty_shipped', 'qty_returned', 'qty_with_courier', 'qty_delivered', 'unit_price', 'discount_amount', 'discount_percent', 'line_total', 'returned_amount', 'lost_amount', 'net_line_total']
            : ['code', 'name', 'size', 'qty', 'unit_price', 'discount_amount', 'discount_percent', 'line_total'];

        $headerRows[] = $lineHeaders;
        $active->fromArray($headerRows, null, 'A1');

        $row = count($headerRows) + 1;
        $lineRows = $tracked ? $display['lines'] : $invoice->lines->map(fn ($line) => ['line' => $line])->all();

        foreach ($lineRows as $lineRow) {
            $line = $lineRow['line'];
            $active->fromArray([$tracked
                ? [
                    $line->item_code_snapshot,
                    $line->item_name_snapshot,
                    $line->size_snapshot,
                    $lineRow['qty_shipped'],
                    $lineRow['qty_returned'],
                    $lineRow['qty_with_courier'],
                    $lineRow['qty_delivered'],
                    (string) $line->unit_price,
                    (string) $line->discount_amount,
                    (string) $line->discount_percent,
                    (string) $line->line_total,
                    (string) $lineRow['returned_amount'],
                    (string) $lineRow['lost_amount'],
                    (string) $lineRow['net_line_total'],
                ]
                : [
                    $line->item_code_snapshot,
                    $line->item_name_snapshot,
                    $line->size_snapshot,
                    $line->qty,
                    (string) $line->unit_price,
                    (string) $line->discount_amount,
                    (string) $line->discount_percent,
                    (string) $line->line_total,
                ]], null, 'A'.$row);
            $row++;
        }

        $filename = 'gross-invoice-'.$invoice->invoice_number.'.xlsx';

        return response()->streamDownload(function () use ($sheet) {
            (new Xlsx($sheet))->save('php://output');
            $sheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
