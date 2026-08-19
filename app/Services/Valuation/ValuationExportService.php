<?php

namespace App\Services\Valuation;

use App\Models\ValuationSheet;
use App\Support\DompdfFonts;
use Barryvdh\DomPDF\Facade\Pdf;

class ValuationExportService
{
    public function downloadPdf(ValuationSheet $sheet): \Barryvdh\DomPDF\PDF
    {
        $sheet->loadMissing('user');
        $fontDir = DompdfFonts::directory();

        return Pdf::loadView('exports.valuation-pdf', [
            'sheet' => $sheet,
            'module' => $sheet->module,
            'input' => $sheet->input,
            'result' => $sheet->result,
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
}
