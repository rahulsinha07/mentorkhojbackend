<?php

namespace App\Model\Mentor;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorDocumentPurchase extends Model
{
    protected $table = 'mentor_document_purchases';

    protected $casts = [
        'amount' => 'float',
        'tax_amount' => 'float',
        'platform_fee' => 'float',
        'mentor_net' => 'float',
    ];

    protected $fillable = [
        'mentor_id',
        'mentor_document_id',
        'mentee_user_id',
        'legacy_order_id',
        'amount',
        'tax_amount',
        'platform_fee',
        'mentor_net',
        'payment_status',
    ];

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(Mentor::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(MentorDocument::class, 'mentor_document_id');
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_user_id');
    }

    public function earnings()
    {
        return $this->hasMany(MentorEarning::class, 'mentor_document_purchase_id');
    }

    public function isPaymentConfirmed(): bool
    {
        return (string) ($this->payment_status ?? 'pending') === 'paid';
    }
}
