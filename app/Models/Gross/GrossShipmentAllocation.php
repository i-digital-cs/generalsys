<?php

namespace App\Models\Gross;

use App\Exceptions\GrossException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrossShipmentAllocation extends Model
{
    protected $table = 'gross_shipment_allocations';

    protected $fillable = [
        'sales_invoice_line_id',
        'shipping_company_id',
        'conversion_factor_snapshot',
        'qty_shipped_pieces',
        'qty_delivered_pieces',
        'qty_returned_pieces',
        'qty_returned_received_pieces',
        'qty_with_courier_pieces',
        'qty_lost_pieces',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor_snapshot' => 'integer',
            'qty_shipped_pieces' => 'integer',
            'qty_delivered_pieces' => 'integer',
            'qty_returned_pieces' => 'integer',
            'qty_returned_received_pieces' => 'integer',
            'qty_with_courier_pieces' => 'integer',
            'qty_lost_pieces' => 'integer',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(GrossSalesInvoiceLine::class, 'sales_invoice_line_id');
    }

    public function shippingCompany(): BelongsTo
    {
        return $this->belongsTo(GrossShippingCompany::class, 'shipping_company_id');
    }

    public function assertInvariant(): void
    {
        $sum = $this->qty_delivered_pieces
            + $this->qty_with_courier_pieces
            + $this->qty_lost_pieces
            + $this->qty_returned_received_pieces;

        if ($sum !== $this->qty_shipped_pieces) {
            throw new GrossException('gross.errors.allocation_invariant');
        }

        $maxReturned = $this->qty_shipped_pieces
            - $this->qty_delivered_pieces
            - $this->qty_lost_pieces;

        if ($this->qty_returned_pieces > $maxReturned) {
            throw new GrossException('gross.errors.allocation_invariant');
        }

        if ($this->qty_returned_received_pieces > $this->qty_returned_pieces) {
            throw new GrossException('gross.errors.allocation_invariant');
        }

        if ($this->returnedPendingHandover() > $this->qty_with_courier_pieces) {
            throw new GrossException('gross.errors.allocation_invariant');
        }
    }

    public function returnedPendingHandover(): int
    {
        return max(0, (int) $this->qty_returned_pieces - (int) $this->qty_returned_received_pieces);
    }

    public function deliverableWithCourier(): int
    {
        return max(0, (int) $this->qty_with_courier_pieces - $this->returnedPendingHandover());
    }
}
