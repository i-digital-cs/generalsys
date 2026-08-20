<?php

namespace App\Services\Valuation;

use App\Models\User;
use App\Models\ValuationSheet;
use App\Support\DompdfFonts;
use Barryvdh\DomPDF\Facade\Pdf;

class ValuationExportService
{
    public function downloadPdf(ValuationSheet $sheet): \Barryvdh\DomPDF\PDF
    {
        $sheet->loadMissing('user');
        $fontDir = DompdfFonts::directory();
        $ids = collect(ValuationFields::normalizeDrivers($sheet->input['drivers'] ?? []))
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $driverUsers = $ids === []
            ? collect()
            : User::query()->whereIn('id', $ids)->get()->keyBy('id');

        return Pdf::loadView('exports.valuation-pdf', [
            'sheet' => $sheet,
            'module' => $sheet->module,
            'input' => $sheet->input,
            'result' => $sheet->result,
            'driverUsers' => $driverUsers,
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
