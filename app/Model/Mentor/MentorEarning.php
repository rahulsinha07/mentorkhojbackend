<?php

namespace App\Model\Mentor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorEarning extends Model
{
    protected $table = 'mentor_earnings';

    protected $casts = [
        'gross' => 'float',
        'fee' => 'float',
        'net' => 'float',
    ];

    protected $fillable = [
        'mentor_id',
        'mentor_booking_id',
        'type',
        'gross',
        'fee',
        'net',
        'status',
    ];

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(Mentor::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(MentorBooking::class, 'mentor_booking_id');
    }
}
