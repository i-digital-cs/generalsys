<?php

namespace App\Models;

use Database\Factories\PayrollAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Manual commission / bonus / advance line on a payslip (schema doc §15).
 */
class PayrollAdjustment extends Model
{
    /** @use HasFactory<PayrollAdjustmentFactory> */
    use HasFactory;

    public const TYPE_DEPARTMENT_COMMISSION = 'DEPARTMENT_COMMISSION';

    public const TYPE_SALES_COMMISSION = 'SALES_COMMISSION';

    public const TYPE_PROFIT_COMMISSION = 'PROFIT_COMMISSION';

    public const TYPE_BONUS = 'BONUS';

    public const TYPE_ADVANCE = 'ADVANCE';

    public const TYPE_OTHER_DEDUCTION = 'OTHER_DEDUCTION';

    protected $fillable = [
        'payroll_record_id',
        'type',
        'amount',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function payrollRecord(): BelongsTo
    {
        return $this->belongsTo(PayrollRecord::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
