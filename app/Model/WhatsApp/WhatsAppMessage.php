<?php

namespace App\Model\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'wa_id',
        'contact_name',
        'direction',
        'wamid',
        'type',
        'body',
        'status',
        'source',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];
}
