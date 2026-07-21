<?php

namespace App\Model\Seminar;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeminarBooking extends Model
{
    protected $table = 'seminar_bookings';

    protected $fillable = [
        'booking_ref',
        'seminar_id',
        'customer_id',
        'name',
        'email',
        'phone',
        'org',
        'details',
        'amount',
        'currency',
        'status',
        'payment_status',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'paid_at',
        'email_sent_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'email_sent_at' => 'datetime',
    ];

    public function seminar(): BelongsTo
    {
        return $this->belongsTo(Seminar::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public static function generateBookingRef(): string
    {
        return 'SKB-' . date('Y') . '-' . str_pad((string) ((int) self::max('id') + 1), 4, '0', STR_PAD_LEFT);
    }

    public function isPaidOrFree(): bool
    {
        return in_array($this->payment_status, ['paid', 'not_required'], true);
    }
}
