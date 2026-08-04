<?php

namespace App\Models;

use Database\Factories\PenaltyRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Dynamic penalty rates (schema doc §13) — Admin-editable in T-4.2.
 */
class PenaltyRule extends Model
{
    /** @use HasFactory<PenaltyRuleFactory> */
    use HasFactory;

    public const TYPE_LATE_ARRIVAL = 'LATE_ARRIVAL';

    public const TYPE_LEAVE_PERMISSION = 'LEAVE_PERMISSION';

    public const TYPE_LATE_PERMISSION = 'LATE_PERMISSION';

    public const TYPE_EXCUSED_ABSENCE = 'EXCUSED_ABSENCE';

    public const TYPE_UNEXCUSED_ABSENCE = 'UNEXCUSED_ABSENCE';

    public const UNIT_MINUTE = 'MINUTE';

    public const UNIT_HOUR = 'HOUR';

    public const UNIT_DAY = 'DAY';

    public const CURRENCY_EGP = 'EGP';

    public const CURRENCY_AED = 'AED';

    /** @return list<string> */
    public static function types(): array
    {
        return [
            self::TYPE_LATE_ARRIVAL,
            self::TYPE_LEAVE_PERMISSION,
            self::TYPE_LATE_PERMISSION,
            self::TYPE_EXCUSED_ABSENCE,
            self::TYPE_UNEXCUSED_ABSENCE,
        ];
    }

    protected $fillable = [
        'name',
        'type',
        'currency',
        'amount_per_unit',
        'unit',
        'double_penalty_multiplier',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount_per_unit' => 'decimal:2',
            'double_penalty_multiplier' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
