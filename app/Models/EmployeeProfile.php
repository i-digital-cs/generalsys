<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfile extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'employee_code',
        'national_id',
        'birth_date',
        'address',
        'phone',
        'gender',
        'religion',
        'marital_status',
        'personal_photo_path',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    /**
     * Age is computed from birth_date — never stored (schema doc §2).
     */
    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
