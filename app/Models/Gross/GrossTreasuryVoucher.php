<?php

namespace App\Models\Gross;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrossTreasuryVoucher extends Model
{
    public const TYPE_PAYMENT = 'PAYMENT';

    public const TYPE_RECEIPT = 'RECEIPT';

    public const PURPOSE_SUPPLIER = 'SUPPLIER';

    public const PURPOSE_EXPENSE = 'EXPENSE';

    public const PURPOSE_COURIER_COD = 'COURIER_COD';

    public const PURPOSE_OTHER = 'OTHER';

    protected $table = 'gross_treasury_vouchers';

    protected $fillable = [
        'voucher_number',
        'type',
        'purpose',
        'account_id',
        'supplier_id',
        'expense_type_id',
        'sales_invoice_id',
        'shipping_company_id',
        'currency',
        'amount',
        'voucher_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'voucher_date' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(GrossTreasuryAccount::class, 'account_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(GrossSupplier::class, 'supplier_id');
    }

    public function expenseType(): BelongsTo
    {
        return $this->belongsTo(GrossExpenseType::class, 'expense_type_id');
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(GrossSalesInvoice::class, 'sales_invoice_id');
    }

    public function shippingCompany(): BelongsTo
    {
        return $this->belongsTo(GrossShippingCompany::class, 'shipping_company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(GrossTreasuryVoucherAttachment::class, 'voucher_id');
    }

    public function isPayment(): bool
    {
        return $this->type === self::TYPE_PAYMENT;
    }

    public function isSupplierPurpose(): bool
    {
        return $this->purpose === self::PURPOSE_SUPPLIER;
    }

    public function isCourierCodPurpose(): bool
    {
        return $this->purpose === self::PURPOSE_COURIER_COD;
    }

    public function isOtherPurpose(): bool
    {
        return $this->purpose === self::PURPOSE_OTHER;
    }
}
