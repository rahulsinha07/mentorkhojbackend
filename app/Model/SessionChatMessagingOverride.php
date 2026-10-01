<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class SessionChatMessagingOverride extends Model
{
    public const FORCE_ON = 'force_on';
    public const FORCE_OFF = 'force_off';

    protected $table = 'session_chat_messaging_overrides';

    protected $fillable = [
        'mentee_user_id',
        'mentor_id',
        'override',
    ];
}
