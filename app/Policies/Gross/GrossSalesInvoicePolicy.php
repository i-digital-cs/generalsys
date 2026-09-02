<?php

namespace App\Policies\Gross;

use App\Models\Gross\GrossSalesInvoice;
use App\Models\User;
use App\Support\GrossAccess;

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
        return GrossAccess::maySell($user) && $invoice->isDraft();
    }

    public function confirm(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::maySell($user);
    }

    public function cancel(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::maySell($user);
    }

    public function refuse(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayRefuse($user);
    }

    public function returnLine(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::maySell($user);
    }

    public function lossLine(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayConfirmLoss($user);
    }

    public function collectCod(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayCollectCourier($user)
            && $invoice->isConfirmed()
            && $invoice->codIsPending();
    }

    /** @deprecated Use collectCod() */
    public function collectShippingFee(User $user, GrossSalesInvoice $invoice): bool
    {
        return $this->collectCod($user, $invoice);
    }

    public function markDelivered(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayCollectCourier($user) && $invoice->canMarkDelivered();
    }

    public function receiveRefused(User $user, GrossSalesInvoice $invoice): bool
    {
        return GrossAccess::mayCollectCourier($user)
            && $invoice->isRefused()
            && $invoice->refusedPiecesPendingHandover() > 0;
    }
}
