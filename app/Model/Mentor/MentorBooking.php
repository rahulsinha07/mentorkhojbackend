<?php

namespace App\Model\Mentor;

use App\CentralLogics\WhatsAppWebLink;
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
        'payment_reminder_email_sent_at' => 'datetime',
        'schedule_notify_sent_at' => 'datetime',
        'session_reminder_24h_sent_at' => 'datetime',
    ];

    protected $fillable = [
        'mentor_id',
        'mentor_service_id',
        'mentee_user_id',
        'session_credit_id',
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
        'booking_source',
        'mentee_booked_email_sent_at',
        'mentor_notify_email_sent_at',
        'mentee_confirmed_email_sent_at',
        'payment_reminder_email_sent_at',
        'schedule_notify_sent_at',
        'session_reminder_24h_sent_at',
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

    public function sessionCredit(): BelongsTo
    {
        return $this->belongsTo(MentorSessionCredit::class, 'session_credit_id');
    }

    public function whatsappMenteeUrl(): ?string
    {
        $mentee = $this->mentee;
        if (!$mentee) {
            return null;
        }

        $mentorName = $this->mentor?->display_name ?: 'your mentor';
        $when = $this->preferred_date?->format('d M Y') ?: '';
        $time = $this->preferred_time ? ' at '.$this->preferred_time : '';

        return WhatsAppWebLink::url(
            $mentee->phone ? (string) $mentee->phone : null,
            WhatsAppWebLink::studentWelcome($mentee->displayName())
                ."\n\nThis is about your session with *{$mentorName}*".($when ? " on {$when}{$time}" : '').'. Reply if you need anything before the call.'
        );
    }

    public function whatsappMentorUrl(): ?string
    {
        $user = $this->mentor?->user;
        if (!$user) {
            return null;
        }

        $menteeName = $this->mentee ? $this->mentee->displayName() : 'a student';
        $when = $this->preferred_date?->format('d M Y') ?: '';
        $time = $this->preferred_time ? ' at '.$this->preferred_time : '';
        $note = 'You have a session with '.$menteeName.($when ? " on {$when}{$time}" : '').'. Reply if you need anything from our team.';

        return WhatsAppWebLink::url(
            $user->phone ? (string) $user->phone : null,
            WhatsAppWebLink::mentorWelcome($this->mentor->display_name ?: $user->displayName(), $note)
        );
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(MentorEarning::class);
    }
}
