<?php

namespace App\Services\Gross;

use App\Exceptions\GrossException;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * COD / shipping-fee collection (money only — never moves stock).
 */
class SalesInvoiceCodCollectionService
{
    public function __construct(
        private readonly SalesInvoiceNetService $net,
        private readonly TreasuryService $treasury,
    ) {}

    /**
     * Confirmed invoices with COD still PENDING (courier collection queue).
     * Only after stock is with the courier.
     */
    public function paginatePendingCollections(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $q = GrossSalesInvoice::query()
            ->with(['shippingCompany', 'salesUser', 'customer'])
            ->where('status', GrossSalesInvoice::STATUS_CONFIRMED)
            ->where('fulfillment_status', GrossSalesInvoice::FULFILLMENT_WITH_COURIER)
            ->where('cod_collection_status', GrossSalesInvoice::COD_PENDING)
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id');

        if (! empty($filters['shipping_company_id'])) {
            $q->where('shipping_company_id', (int) $filters['shipping_company_id']);
        }

        return $q->paginate(min($perPage, 50))->withQueryString();
    }

    /**
     * @return Collection<int, GrossSalesInvoice>
     */
    public function listCollectedWithoutTreasury(int $limit = 100): Collection
    {
        return GrossSalesInvoice::query()
            ->where('cod_collection_status', GrossSalesInvoice::COD_COLLECTED)
            ->whereNotNull('shipping_company_id')
            ->whereDoesntHave('courierCodTreasuryVoucher')
            ->with('shippingCompany')
            ->orderByDesc('cod_collected_at')
            ->orderByDesc('id')
            ->limit(min($limit, 100))
            ->get();
    }

    /**
     * COD / fee flags applied when stock is handed to courier.
     *
     * @return array{cod_collection_status: string, shipping_fee_status: string}
     */
    public function codFieldsOnHandToCourier(GrossSalesInvoice $invoice): array
    {
        $hasCourier = $invoice->shipping_company_id !== null;
        $total = (float) $invoice->total;
        $fee = (float) $invoice->shipping_fee;

        return [
            'cod_collection_status' => ($hasCourier && $total > 0)
                ? GrossSalesInvoice::COD_PENDING
                : GrossSalesInvoice::COD_NONE,
            'shipping_fee_status' => $fee > 0
                ? GrossSalesInvoice::SHIPPING_FEE_PENDING
                : GrossSalesInvoice::SHIPPING_FEE_NONE,
        ];
    }

    /**
     * Collect merchandise COD from courier (money only).
     * Does not move returned pieces into warehouse — use receiveReturnedPiecesFromCourier().
     *
     * @param  array{post_to_treasury?: bool, treasury_account_id?: int|null}  $options
     */
    public function collectCod(GrossSalesInvoice $invoice, User $actor, array $options = []): GrossSalesInvoice
    {
        return DB::transaction(function () use ($invoice, $actor, $options) {
            /** @var GrossSalesInvoice $locked */
            $locked = GrossSalesInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->cod_collection_status === GrossSalesInvoice::COD_COLLECTED) {
                return $locked->fresh(['lines', 'shippingCompany', 'codCollectedByUser', 'courierCodTreasuryVoucher']);
            }

            if ($locked->status !== GrossSalesInvoice::STATUS_CONFIRMED) {
                throw GrossException::notConfirmed();
            }

            if ($locked->fulfillment_status !== GrossSalesInvoice::FULFILLMENT_WITH_COURIER) {
                throw GrossException::notWithCourier();
            }

            if ($locked->cod_collection_status !== GrossSalesInvoice::COD_PENDING) {
                throw GrossException::codNotPending();
            }

            $locked->load(['lines.allocation']);
            $this->net->recalculateInvoice($locked);
            $locked = $locked->fresh(['lines.allocation', 'lines.item']);

            $amount = round($locked->collectibleAmount(), 2);

            $feeUpdate = [];
            if ($locked->shipping_fee_status === GrossSalesInvoice::SHIPPING_FEE_PENDING) {
                $feeUpdate = [
                    'shipping_fee_status' => GrossSalesInvoice::SHIPPING_FEE_COLLECTED,
                    'shipping_fee_collected_at' => now(),
                    'shipping_fee_collected_by' => $actor->id,
                ];
            }

            $locked->update(array_merge([
                'cod_collection_status' => GrossSalesInvoice::COD_COLLECTED,
                'cod_collected_amount' => $amount,
                'cod_collected_at' => now(),
                'cod_collected_by' => $actor->id,
            ], $feeUpdate));

            $locked = $locked->fresh(['lines', 'shippingCompany', 'codCollectedByUser']);

            if (! empty($options['post_to_treasury']) && ! empty($options['treasury_account_id'])) {
                $this->treasury->createCourierCodReceipt($actor, [
                    'sales_invoice_id' => $locked->id,
                    'account_id' => (int) $options['treasury_account_id'],
                    'voucher_date' => now()->toDateString(),
                ]);
            }

            return $locked->fresh(['lines', 'shippingCompany', 'codCollectedByUser', 'courierCodTreasuryVoucher']);
        });
    }

    /** @deprecated Use collectCod() — kept for tests migrating from fee-only collection. */
    public function collectShippingFee(GrossSalesInvoice $invoice, User $actor): GrossSalesInvoice
    {
        return $this->collectCod($invoice, $actor);
    }
}
