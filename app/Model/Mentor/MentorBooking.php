<?php

namespace App\Model\Mentor;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MentorBooking extends Model
{
    protected $table = 'mentor_bookings';

    protected $casts = [
        'preferred_date' => 'date',
        'amount' => 'float',
        'tax_amount' => 'float',
        'platform_fee' => 'float',
        'mentor_net' => 'float',
        'mentee_booked_email_sent_at' => 'datetime',
        'mentor_notify_email_sent_at' => 'datetime',
        'mentee_confirmed_email_sent_at' => 'datetime',
    ];

    protected $fillable = [
        'mentor_id',
        'mentor_service_id',
        'mentee_user_id',
        'legacy_order_id',
        'preferred_date',
        'preferred_time',
        'mentee_note',
        'status',
        'amount',
        'tax_amount',
        'platform_fee',
        'mentor_net',
        'payment_status',
        'mentee_booked_email_sent_at',
        'mentor_notify_email_sent_at',
        'mentee_confirmed_email_sent_at',
    ];

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(Mentor::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(MentorService::class, 'mentor_service_id');
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_user_id');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(MentorEarning::class);
    }
}
