<?php

namespace App\Models;

use Database\Factories\PayrollRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Monthly payslip draft / approved / paid (schema doc §14).
 */
class PayrollRecord extends Model
{
    /** @use HasFactory<PayrollRecordFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_PAID = 'PAID';

    protected $fillable = [
        'user_id',
        'month',
        'year',
        'currency',
        'base_salary',
        'full_time_days',
        'online_half_days',
        'online_full_days',
        'rest_days',
        'extra_shift_hours',
        'overtime_hours',
        'overtime_multiplier',
        'friday_work_days',
        'day_off_work_days',
        'official_holiday_work_days',
        'hours_deduction',
        'hours_deduction_amount',
        'excused_absence_days',
        'unexcused_absence_days',
        'late_minutes_total',
        'full_time_pay',
        'online_half_pay',
        'online_full_pay',
        'overtime_pay',
        'friday_bonus',
        'day_off_bonus',
        'official_holiday_bonus',
        'extra_days',
        'extra_pay',
        'late_penalty',
        'leave_permission_penalty',
        'late_permission_penalty',
        'excused_absence_penalty',
        'unexcused_absence_penalty',
        'forced_penalty_surcharge',
        'advance_deductions',
        'gross_pay',
        'total_deductions',
        'net_pay',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'extra_shift_hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'overtime_multiplier' => 'decimal:2',
            'hours_deduction' => 'decimal:2',
            'hours_deduction_amount' => 'decimal:2',
            'full_time_pay' => 'decimal:2',
            'online_half_pay' => 'decimal:2',
            'online_full_pay' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'friday_bonus' => 'decimal:2',
            'day_off_bonus' => 'decimal:2',
            'official_holiday_bonus' => 'decimal:2',
            'extra_pay' => 'decimal:2',
            'late_penalty' => 'decimal:2',
            'leave_permission_penalty' => 'decimal:2',
            'late_permission_penalty' => 'decimal:2',
            'excused_absence_penalty' => 'decimal:2',
            'unexcused_absence_penalty' => 'decimal:2',
            'forced_penalty_surcharge' => 'decimal:2',
            'advance_deductions' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }
}
