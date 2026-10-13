<?php

namespace App\Services\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossSalesInvoiceLine;
use App\Models\Gross\GrossShipmentAllocation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sales fulfillment pipeline: confirm → packing → ready → hand to courier.
 * Stock leaves the warehouse only on handToCourier (not on confirm).
 */
class SalesInvoiceFulfillmentService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly SalesInvoiceNetService $net,
        private readonly SalesInvoiceCodCollectionService $cod,
    ) {}

    /**
     * Sidebar queues for مسار الشحنة.
     *
     * @param  'preparing'|'ready'|'with_courier'|'delivered'|'returned'  $queue
     */
    public function paginateFulfillmentQueue(string $queue, int $perPage = 25): LengthAwarePaginator
    {
        $q = GrossSalesInvoice::query()
            ->with(['customer', 'shippingCompany', 'salesUser'])
            ->orderByDesc('id');

        match ($queue) {
            'preparing' => $q->where('status', GrossSalesInvoice::STATUS_CONFIRMED)
                ->where('fulfillment_status', GrossSalesInvoice::FULFILLMENT_PREPARING),
            'ready' => $q->where('status', GrossSalesInvoice::STATUS_CONFIRMED)
                ->where('fulfillment_status', GrossSalesInvoice::FULFILLMENT_READY_WAREHOUSE),
            'with_courier' => $q->where('status', GrossSalesInvoice::STATUS_CONFIRMED)
                ->where('fulfillment_status', GrossSalesInvoice::FULFILLMENT_WITH_COURIER),
            'delivered' => $q->where('status', GrossSalesInvoice::STATUS_DELIVERED),
            'returned' => $q->where('status', GrossSalesInvoice::STATUS_REFUSED),
            default => $q->whereRaw('1 = 0'),
        };

        return $q->paginate(min($perPage, 50))->withQueryString();
    }

    public function confirm(GrossSalesInvoice $invoice, ?User $actor = null): GrossSalesInvoice
    {
        return DB::transaction(function () use ($invoice) {
            /** @var GrossSalesInvoice $locked */
            $locked = GrossSalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === GrossSalesInvoice::STATUS_CONFIRMED
                || $locked->status === GrossSalesInvoice::STATUS_DELIVERED) {
                return $locked->fresh(['lines.allocation', 'lines.item']);
            }

            if ($locked->status !== GrossSalesInvoice::STATUS_DRAFT) {
                throw GrossException::invalidTransition();
            }

            $locked->load(['lines.item']);

            if ($locked->lines->isEmpty()) {
                throw GrossException::invalidQty();
            }

            // Confirm starts packing (جاري التجهيز). Stock leaves warehouse only on handToCourier.
            $locked->update([
                'status' => GrossSalesInvoice::STATUS_CONFIRMED,
                'fulfillment_status' => GrossSalesInvoice::FULFILLMENT_PREPARING,
                'confirmed_at' => now(),
            ]);

            $this->net->recalculateInvoice($locked->fresh(['lines.allocation']));

            return $locked->fresh(['lines.allocation', 'lines.item']);
        });
    }

    public function storePackingImage(GrossSalesInvoice $invoice, UploadedFile $file, User $actor): GrossSalesInvoice
    {
        return DB::transaction(function () use ($invoice, $file, $actor) {
            /** @var GrossSalesInvoice $locked */
            $locked = GrossSalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== GrossSalesInvoice::STATUS_CONFIRMED
                || $locked->fulfillment_status !== GrossSalesInvoice::FULFILLMENT_PREPARING) {
                throw GrossException::notPreparing();
            }

            if ($locked->packing_image_path && Storage::disk('local')->exists($locked->packing_image_path)) {
                Storage::disk('local')->delete($locked->packing_image_path);
            }

            $ext = strtolower((string) $file->getClientOriginalExtension());
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $ext = 'jpg';
            }

            $path = $file->storeAs(
                'gross-sales-packing/'.$locked->id,
                Str::random(40).'.'.$ext,
                'local'
            );

            $locked->update([
                'packing_image_path' => $path,
                'packing_image_original_name' => Str::limit((string) $file->getClientOriginalName(), 180, ''),
                'packing_image_mime' => $file->getMimeType(),
                'packing_image_size_bytes' => $file->getSize(),
                'packing_uploaded_at' => now(),
                'packing_uploaded_by' => $actor->id,
            ]);

            return $locked->fresh(['lines', 'packingUploadedByUser']);
        });
    }

    public function markReadyInWarehouse(GrossSalesInvoice $invoice): GrossSalesInvoice
    {
        return DB::transaction(function () use ($invoice) {
            /** @var GrossSalesInvoice $locked */
            $locked = GrossSalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== GrossSalesInvoice::STATUS_CONFIRMED
                || $locked->fulfillment_status !== GrossSalesInvoice::FULFILLMENT_PREPARING) {
                throw GrossException::notPreparing();
            }

            if (! $locked->hasPackingImage()) {
                throw GrossException::packingImageRequired();
            }

            $locked->update([
                'fulfillment_status' => GrossSalesInvoice::FULFILLMENT_READY_WAREHOUSE,
            ]);

            return $locked->fresh(['lines.allocation', 'lines.item']);
        });
    }

    /**
     * Hand invoice to courier: stock ON_HAND → WITH_COURIER + allocations + COD flags.
     */
    public function handToCourier(GrossSalesInvoice $invoice, ?User $actor = null): GrossSalesInvoice
    {
        return DB::transaction(function () use ($invoice, $actor) {
            /** @var GrossSalesInvoice $locked */
            $locked = GrossSalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->fulfillment_status === GrossSalesInvoice::FULFILLMENT_WITH_COURIER
                && $locked->status === GrossSalesInvoice::STATUS_CONFIRMED) {
                return $locked->fresh(['lines.allocation', 'lines.item']);
            }

            if ($locked->status !== GrossSalesInvoice::STATUS_CONFIRMED
                || $locked->fulfillment_status !== GrossSalesInvoice::FULFILLMENT_READY_WAREHOUSE) {
                throw GrossException::notReadyWarehouse();
            }

            $locked->load(['lines.item', 'lines.allocation']);

            foreach ($locked->lines as $line) {
                if ($line->allocation !== null) {
                    continue;
                }

                $item = $line->item;
                if ($item === null) {
                    throw GrossException::invalidTransition();
                }

                $this->stock->applySaleOut(
                    $item,
                    (int) $line->qty_pieces,
                    (int) $line->conversion_factor_snapshot,
                    $line->unit,
                    (int) $line->qty,
                    GrossSalesInvoiceLine::class,
                    (int) $line->id,
                    $actor,
                );

                GrossShipmentAllocation::query()->create([
                    'sales_invoice_line_id' => $line->id,
                    'shipping_company_id' => $locked->shipping_company_id,
                    'conversion_factor_snapshot' => $line->conversion_factor_snapshot,
                    'qty_shipped_pieces' => $line->qty_pieces,
                    'qty_delivered_pieces' => 0,
                    'qty_returned_pieces' => 0,
                    'qty_with_courier_pieces' => $line->qty_pieces,
                    'qty_lost_pieces' => 0,
                ]);
            }

            $locked->update([
                'fulfillment_status' => GrossSalesInvoice::FULFILLMENT_WITH_COURIER,
            ]);

            $this->net->recalculateInvoice($locked->fresh(['lines.allocation']));
            $locked = $locked->fresh(['lines.allocation']);
            $locked->update($this->cod->codFieldsOnHandToCourier($locked));

            return $locked->fresh(['lines.allocation', 'lines.item']);
        });
    }
}
