<?php

namespace App\Model;

use App\Model\Mentor\Mentor;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionChatMessage extends Model
{
    protected $table = 'session_chat_messages';

    protected $fillable = [
        'mentee_user_id',
        'mentor_id',
        'demo_booking_id',
        'mentor_booking_id',
        'sender_role',
        'body',
    ];

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_user_id');
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(Mentor::class, 'mentor_id');
    }
}
