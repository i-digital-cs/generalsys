<?php

namespace App\Support\GeneralHouse;

final class GhCreditNoteStatus
{
    public const DRAFT = 'DRAFT';

    public const PENDING_APPROVAL = 'PENDING_APPROVAL';

    public const APPROVED = 'APPROVED';

    public const ISSUED = 'ISSUED';

    public const REJECTED = 'REJECTED';

    public const CANCELLED = 'CANCELLED';

    /** @var list<string> */
    public const ALL = [
        self::DRAFT,
        self::PENDING_APPROVAL,
        self::APPROVED,
        self::ISSUED,
        self::REJECTED,
        self::CANCELLED,
    ];
}
