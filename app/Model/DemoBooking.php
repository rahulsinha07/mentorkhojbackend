<?php

namespace App\Model;

use App\CentralLogics\DemoBookingClaimLogic;
use App\CentralLogics\WhatsAppDemoBookingModule;
use App\CentralLogics\WhatsAppWebLink;
use App\Model\Mentor\Mentor;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'profile_invite_token',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assignedMentors(): BelongsToMany
    {
        return $this->belongsToMany(Mentor::class, 'demo_booking_mentors', 'demo_booking_id', 'mentor_id')
            ->withPivot(['paid_session_done', 'assigned_at', 'assignment_email_sent_at'])
            ->withTimestamps();
    }

    public function whatsappWebDigits(): ?string
    {
        return WhatsAppWebLink::digits((string) $this->phone);
    }

    public function whatsappWebUrl(): ?string
    {
        return WhatsAppWebLink::url(
            (string) $this->phone,
            WhatsAppWebLink::studentWelcome(
                trim((string) $this->name),
                $this->demoProgramLabel(),
                trim((string) $this->booking_ref) ?: null,
                DemoBookingClaimLogic::profileCtaUrl($this)
            )
        );
    }

    public function demoProgramLabel(): string
    {
        $label = trim((string) $this->category_label);
        if ($label !== '') {
            return $label;
        }

        $key = WhatsAppDemoBookingModule::verticalKey(
            $this->vertical ? (string) $this->vertical : null,
            $this->category ? (string) $this->category : null
        );

        return [
            'neet' => 'NEET',
            'jee' => 'IIT-JEE',
            'tech' => 'Tech',
            'ai' => 'AI/ML',
        ][$key] ?? 'mentorship';
    }
}
