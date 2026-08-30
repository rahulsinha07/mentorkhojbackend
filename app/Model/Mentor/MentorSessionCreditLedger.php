<?php

namespace App\Model\Mentor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorSessionCreditLedger extends Model
{
    protected $table = 'mentor_session_credit_ledger';

    protected $fillable = [
        'credit_id',
        'type',
        'amount',
        'mentor_booking_id',
        'admin_id',
        'note',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function credit(): BelongsTo
    {
        return $this->belongsTo(MentorSessionCredit::class, 'credit_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(MentorBooking::class, 'mentor_booking_id');
    }
}
