<?php

namespace App\Policies\Gross;

use App\Models\Gross\GrossSalesInvoice;
use App\Models\User;
use App\Support\GrossAccess;

/**
 * Sales invoices: create = maySell; view = Gross access.
 * All mutations (edit, cancel, confirm, fulfillment, COD, returns, media) = Manager (Admin) only.
 */
class GrossSalesInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return GrossAccess::mayAccessGross($user);
    }

    public function view(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayAccessGross($user);
    }

    public function create(User $user): bool
    {
        return GrossAccess::maySell($user);
    }

    public function update(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutateSalesInvoice($user) && $invoice->isDraft();
    }

    public function confirm(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutateSalesInvoice($user);
    }

    public function cancel(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutateSalesInvoice($user);
    }

    public function returnLine(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutateSalesInvoice($user);
    }

    public function lossLine(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutateSalesInvoice($user)
            && GrossAccess::mayConfirmLoss($user);
    }

    public function collectCod(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutateSalesInvoice($user)
            && $invoice->isConfirmed()
            && $invoice->isWithCourier()
            && $invoice->codIsPending();
    }

    /** @deprecated Use collectCod() */
    public function collectShippingFee(User $user, GrossSalesInvoice $invoice): bool
    {
        return $this->collectCod($user, $invoice);
    }

    public function markDelivered(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutateSalesInvoice($user)
            && $invoice->isWithCourier()
            && $invoice->canMarkDelivered();
    }

    public function storePackingImage(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutateSalesInvoice($user)
            && $invoice->isConfirmed()
            && $invoice->isPreparing();
    }

    public function markReadyInWarehouse(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutateSalesInvoice($user)
            && $invoice->isConfirmed()
            && $invoice->isPreparing()
            && $invoice->hasPackingImage();
    }

    public function handToCourier(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutateSalesInvoice($user)
            && $invoice->isConfirmed()
            && $invoice->isReadyInWarehouse();
    }

    public function refuse(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutateSalesInvoice($user)
            && $invoice->isConfirmed()
            && $invoice->isWithCourier();
    }

    public function receiveReturnedPieces(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutateSalesInvoice($user)
            && $invoice->returnedPiecesPendingHandover() > 0;
    }

    /** @deprecated Use receiveReturnedPieces() */
    public function receiveRefused(User $user, GrossSalesInvoice $invoice): bool
    {
        return $this->receiveReturnedPieces($user, $invoice);
    }

    public function manageMedia(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutateSalesInvoice($user);
    }

    public function deleteMedia(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayAdminMutateSalesInvoice($user);
    }
}
