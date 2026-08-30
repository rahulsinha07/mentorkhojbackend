<?php

namespace App\Model\Invoice;

use App\Model\Admin;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $table = 'invoices';

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'payment_date' => 'date',
        'subtotal' => 'float',
        'discount_total' => 'float',
        'taxable_amount' => 'float',
        'cgst' => 'float',
        'sgst' => 'float',
        'igst' => 'float',
        'other_tax' => 'float',
        'additional_charges' => 'float',
        'round_off' => 'float',
        'total_amount' => 'float',
        'amount_paid' => 'float',
        'balance_due' => 'float',
        'invoice_number_manual' => 'boolean',
    ];

    protected $fillable = [
        'invoice_number',
        'status',
        'invoice_date',
        'due_date',
        'payment_date',
        'currency',
        'place_of_supply',
        'reference_number',
        'tax_mode',
        'invoice_customer_id',
        'user_id',
        'source_type',
        'source_id',
        'customer_name',
        'customer_type',
        'customer_company',
        'customer_email',
        'customer_phone',
        'billing_address',
        'billing_city',
        'billing_state',
        'billing_country',
        'billing_postal_code',
        'shipping_address',
        'shipping_city',
        'shipping_state',
        'shipping_country',
        'shipping_postal_code',
        'customer_gstin',
        'customer_pan',
        'customer_external_id',
        'subtotal',
        'discount_total',
        'taxable_amount',
        'cgst',
        'sgst',
        'igst',
        'other_tax',
        'additional_charges',
        'round_off',
        'total_amount',
        'amount_paid',
        'balance_due',
        'payment_status',
        'payment_method',
        'transaction_id',
        'customer_notes',
        'terms',
        'invoice_number_manual',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function invoiceCustomer(): BelongsTo
    {
        return $this->belongsTo(InvoiceCustomer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'generated', 'pending', 'partially_paid'], true);
    }
}
