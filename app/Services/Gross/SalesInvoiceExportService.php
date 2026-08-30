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
    public function downloadPdf(GrossSalesInvoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->loadMissing(['lines', 'salesUser', 'customer', 'shippingCompany']);

        $fontDir = DompdfFonts::directory();

        return Pdf::loadView('exports.gross-sales-invoice-pdf', [
            'invoice' => $invoice,
        ])
            ->setPaper('a4')
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
        $invoice->loadMissing(['lines', 'salesUser']);

        $sheet = new Spreadsheet;
        $active = $sheet->getActiveSheet();
        $active->setTitle('invoice');
        $active->fromArray([
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
            ['notes', (string) ($invoice->notes ?? '')],
            [],
            ['code', 'name', 'size', 'qty', 'unit_price', 'discount_amount', 'discount_percent', 'line_total'],
        ], null, 'A1');

        $row = 16;
        foreach ($invoice->lines as $line) {
            $active->fromArray([[
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
