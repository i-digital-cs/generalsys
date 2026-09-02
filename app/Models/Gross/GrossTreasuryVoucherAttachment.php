<?php

namespace App\Models\Gross;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrossTreasuryVoucherAttachment extends Model
{
    protected $table = 'gross_treasury_voucher_attachments';

    protected $fillable = [
        'voucher_id',
        'original_name',
        'file_path',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(GrossTreasuryVoucher::class, 'voucher_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
