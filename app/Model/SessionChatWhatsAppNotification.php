<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class SessionChatWhatsAppNotification extends Model
{
    protected $table = 'session_chat_whatsapp_notifications';

    protected $fillable = [
        'mentee_user_id',
        'mentor_id',
        'sender_role',
        'notified_date',
        'send_count',
    ];

    protected $casts = [
        'notified_date' => 'date',
    ];
}
