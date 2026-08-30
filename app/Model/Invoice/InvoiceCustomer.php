<?php

namespace App\Model\Invoice;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceCustomer extends Model
{
    protected $table = 'invoice_customers';

    protected $fillable = [
        'user_id',
        'name',
        'customer_type',
        'company_name',
        'email',
        'phone',
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
        'gstin',
        'pan',
        'external_customer_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
