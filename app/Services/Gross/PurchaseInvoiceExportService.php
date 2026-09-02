<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossPurchaseInvoice;
use App\Support\DompdfFonts;
use Barryvdh\DomPDF\Facade\Pdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseInvoiceExportService
{
    public function __construct(
        private readonly PurchaseInvoiceDisplayService $display,
    ) {}

    public function downloadPdf(GrossPurchaseInvoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->loadMissing(['lines.item', 'supplier', 'creator']);
        $display = $this->display->build($invoice);

        $fontDir = DompdfFonts::directory();

        return Pdf::loadView('exports.gross-purchase-invoice-pdf', [
            'invoice' => $invoice,
            'display' => $display,
        ])
            ->setPaper('a4', ($display['has_returns'] ?? false) ? 'landscape' : 'portrait')
            ->setOptions([
                'defaultFont' => 'Tajawal',
                'fontDir' => $fontDir,
                'fontCache' => $fontDir,
                'isRemoteEnabled' => false,
                'chroot' => realpath(base_path()) ?: base_path(),
            ]);
    }

    public function downloadExcel(GrossPurchaseInvoice $invoice): StreamedResponse
    {
        $invoice->loadMissing(['lines.item', 'creator']);
        $display = $this->display->build($invoice);
        $hasReturns = (bool) ($display['has_returns'] ?? false);

        $sheet = new Spreadsheet;
        $active = $sheet->getActiveSheet();
        $active->setTitle('purchase');

        $headerRows = [
            ['invoice_number', $invoice->invoice_number],
            ['status', $invoice->status],
            ['supplier', $invoice->supplier_name],
            ['phone', $invoice->supplier_phone],
            ['currency', $invoice->currency],
            ['subtotal', (string) $invoice->subtotal],
            ['discount_amount', (string) $invoice->discount_amount],
            ['discount_percent', (string) $invoice->discount_percent],
            ['total', (string) $invoice->total],
        ];

        if ($hasReturns) {
            $headerRows[] = ['returned_merchandise', (string) ($display['returned_merchandise'] ?? 0)];
            $headerRows[] = ['net_subtotal', (string) ($display['net_subtotal'] ?? 0)];
            $headerRows[] = ['net_discount', (string) ($display['net_discount'] ?? 0)];
            $headerRows[] = ['net_total', (string) ($display['net_grand_total'] ?? 0)];
        }

        $headerRows[] = ['notes', (string) ($invoice->notes ?? '')];
        $headerRows[] = [];

        $lineHeaders = $hasReturns
            ? ['code', 'name', 'size', 'qty_purchased', 'qty_returned', 'qty_remaining', 'buy_price', 'sale_price', 'line_total', 'returned_amount', 'net_line_total']
            : ['code', 'name', 'size', 'qty', 'buy_price', 'sale_price', 'line_total'];

        $headerRows[] = $lineHeaders;
        $active->fromArray($headerRows, null, 'A1');

        $row = count($headerRows) + 1;
        $lineRows = $hasReturns ? $display['lines'] : $invoice->lines->map(fn ($line) => ['line' => $line])->all();

        foreach ($lineRows as $lineRow) {
            $line = $lineRow['line'];
            $active->fromArray([$hasReturns
                ? [
                    $line->item_code_snapshot,
                    $line->item_name_snapshot,
                    $line->size_snapshot,
                    $lineRow['qty_purchased'],
                    $lineRow['qty_returned'],
                    $lineRow['qty_remaining'],
                    (string) $line->buy_price,
                    (string) $line->sale_price,
                    (string) $line->line_total,
                    (string) $lineRow['returned_amount'],
                    (string) $lineRow['net_line_total'],
                ]
                : [
                    $line->item_code_snapshot,
                    $line->item_name_snapshot,
                    $line->size_snapshot,
                    $line->qty,
                    (string) $line->buy_price,
                    (string) $line->sale_price,
                    (string) $line->line_total,
                ]], null, 'A'.$row);
            $row++;
        }

        $filename = 'gross-purchase-'.$invoice->invoice_number.'.xlsx';

        return response()->streamDownload(function () use ($sheet) {
            (new Xlsx($sheet))->save('php://output');
            $sheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
