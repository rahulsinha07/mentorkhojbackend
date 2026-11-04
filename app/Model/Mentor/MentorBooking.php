<?php

namespace App\Model\Mentor;

use App\Model\Mentor\MentorBookingEvent;
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
        'scheduled_at' => 'datetime',
        'amount' => 'float',
        'tax_amount' => 'float',
        'platform_fee' => 'float',
        'mentor_net' => 'float',
        'reschedule_proposed_date' => 'date',
        'reschedule_requested_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
        'feedback_requested_at' => 'datetime',
        'mentee_booked_email_sent_at' => 'datetime',
        'mentor_notify_email_sent_at' => 'datetime',
        'mentee_confirmed_email_sent_at' => 'datetime',
        'payment_reminder_email_sent_at' => 'datetime',
        'schedule_notify_sent_at' => 'datetime',
        'session_reminder_24h_sent_at' => 'datetime',
        'session_reminder_1h_sent_at' => 'datetime',
    ];

    protected $fillable = [
        'mentor_id',
        'mentor_service_id',
        'mentee_user_id',
        'session_credit_id',
        'legacy_order_id',
        'session_number',
        'total_sessions',
        'duration_minutes',
        'session_type',
        'timezone',
        'preferred_date',
        'preferred_time',
        'scheduled_at',
        'meeting_url',
        'mentee_note',
        'mentor_notes',
        'mentee_notes',
        'status',
        'amount',
        'tax_amount',
        'platform_fee',
        'mentor_net',
        'payment_status',
        'booking_source',
        'cancellation_reason',
        'cancelled_by_user_id',
        'cancelled_at',
        'reschedule_reason',
        'reschedule_requested_by_user_id',
        'reschedule_requested_at',
        'reschedule_proposed_date',
        'reschedule_proposed_time',
        'completed_by_user_id',
        'completed_at',
        'feedback_requested_at',
        'mentee_booked_email_sent_at',
        'mentor_notify_email_sent_at',
        'mentee_confirmed_email_sent_at',
        'payment_reminder_email_sent_at',
        'schedule_notify_sent_at',
        'session_reminder_24h_sent_at',
        'session_reminder_1h_sent_at',
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

    public function events(): HasMany
    {
        return $this->hasMany(MentorBookingEvent::class, 'mentor_booking_id')->orderByDesc('created_at');
    }
}
