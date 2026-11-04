<?php

namespace App\Model\Mentor;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorSessionNotification extends Model
{
    protected $table = 'mentor_session_notifications';

    protected $fillable = [
        'mentor_booking_id',
        'session_credit_id',
        'recipient_user_id',
        'recipient_email',
        'notification_type',
        'scheduled_for',
        'sent_at',
        'status',
        'error_message',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(MentorBooking::class, 'mentor_booking_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
