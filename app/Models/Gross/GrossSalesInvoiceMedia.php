<?php

namespace App\Models\Gross;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrossSalesInvoiceMedia extends Model
{
    protected $table = 'gross_sales_invoice_media';

    protected $fillable = [
        'sales_invoice_id',
        'title',
        'description',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(GrossSalesInvoice::class, 'sales_invoice_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
