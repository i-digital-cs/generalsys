<?php

namespace App\Models;

use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'hire_date',
        'contract_start',
        'contract_end',
        'contract_type',
        'document_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'contract_start' => 'date',
            'contract_end' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class, 'document_id');
    }
}
