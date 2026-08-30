<?php

namespace App\Model\Invoice;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $table = 'invoice_items';

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'discount' => 'float',
        'tax_rate' => 'float',
        'tax_amount' => 'float',
        'line_total' => 'float',
        'sort_order' => 'integer',
    ];

    protected $fillable = [
        'invoice_id',
        'sort_order',
        'service_name',
        'description',
        'sku',
        'quantity',
        'unit',
        'unit_price',
        'discount',
        'discount_type',
        'tax_rate',
        'tax_type',
        'tax_amount',
        'line_total',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
