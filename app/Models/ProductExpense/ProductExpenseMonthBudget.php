<?php

namespace App\Models\ProductExpense;

use App\Models\Gross\GrossExpenseType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductExpenseMonthBudget extends Model
{
    protected $table = 'product_expense_month_budgets';

    protected $fillable = [
        'expense_type_id',
        'year',
        'month',
        'expected_amount',
        'currency',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'expected_amount' => 'decimal:2',
        ];
    }

    public function expenseType(): BelongsTo
    {
        return $this->belongsTo(GrossExpenseType::class, 'expense_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
