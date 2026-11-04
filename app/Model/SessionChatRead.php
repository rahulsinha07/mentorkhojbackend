<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class SessionChatRead extends Model
{
    protected $table = 'session_chat_reads';

    protected $fillable = [
        'user_id',
        'mentor_id',
        'mentee_user_id',
        'last_read_message_id',
    ];
}
