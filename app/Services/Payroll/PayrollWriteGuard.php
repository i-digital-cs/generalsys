<?php

namespace App\Services\Payroll;

use App\Models\PayrollRecord;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Authoritative draft-only write boundary for payroll money mutations.
 *
 * Controllers/policies must still authorize actors; this guard blocks
 * APPROVED/PAID mutation even if a caller bypasses HTTP authorization.
 */
final class PayrollWriteGuard
{
    /**
     * @throws AuthorizationException
     */
    public function assertDraft(PayrollRecord $record): void
    {
        if ($record->status === PayrollRecord::STATUS_DRAFT) {
            return;
        }

        throw new AuthorizationException(
            'Only DRAFT payroll records can be edited.'
        );
    }

    public function isDraft(PayrollRecord $record): bool
    {
        return $record->status === PayrollRecord::STATUS_DRAFT;
    }

    public function isSettled(PayrollRecord $record): bool
    {
        return in_array($record->status, [
            PayrollRecord::STATUS_APPROVED,
            PayrollRecord::STATUS_PAID,
        ], true);
    }
}
