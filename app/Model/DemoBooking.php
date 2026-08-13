<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class DemoBooking extends Model
{
    protected $table = 'demo_bookings';

    protected $fillable = [
        'booking_ref',
        'name',
        'phone',
        'email',
        'category',
        'category_label',
        'stage',
        'subjects',
        'source',
        'vertical',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'message',
        'status',
        'email_sent_at',
        'admin_notes',
        'last_communication_at',
        'user_id',
    ];

    protected $casts = [
        'subjects' => 'array',
        'email_sent_at' => 'datetime',
        'last_communication_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return ['new', 'contacted', 'scheduled', 'completed', 'converted', 'cancelled', 'no_show'];
    }
}
