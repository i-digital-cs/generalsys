<?php

namespace Tests\Support;

use App\Models\Gross\GrossSalesInvoice;
use App\Models\User;
use App\Services\Gross\SalesInvoiceService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait ShipsGrossSalesToCourier
{
    /**
     * Confirm → packing image → ready warehouse → hand to courier (stock out).
     */
    protected function shipToCourier(GrossSalesInvoice $invoice, User $actor): GrossSalesInvoice
    {
        Storage::fake('local');
        $svc = app(SalesInvoiceService::class);
        $invoice = $svc->confirm($invoice, $actor);
        $invoice = $svc->storePackingImage(
            $invoice,
            UploadedFile::fake()->image('packing.jpg', 200, 200),
            $actor
        );
        $invoice = $svc->markReadyInWarehouse($invoice);

        return $svc->handToCourier($invoice, $actor);
    }
}
