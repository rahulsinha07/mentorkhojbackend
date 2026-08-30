<?php

namespace App\Model\Mentor;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MentorSessionCredit extends Model
{
    protected $table = 'mentor_session_credits';

    protected $fillable = [
        'mentee_user_id',
        'mentor_id',
        'credits_total',
        'credits_used',
        'notes',
        'granted_by_admin_id',
    ];

    protected $casts = [
        'credits_total' => 'integer',
        'credits_used' => 'integer',
    ];

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_user_id');
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(Mentor::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(MentorSessionCreditLedger::class, 'credit_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(MentorBooking::class, 'session_credit_id');
    }

    public function remaining(): int
    {
        return max(0, (int) $this->credits_total - (int) $this->credits_used);
    }

    public function openScheduledCount(): int
    {
        return (int) $this->bookings()
            ->where('booking_source', 'credit')
            ->whereNotIn('status', ['completed', 'cancelled', 'refunded'])
            ->count();
    }

    public function availableToSchedule(): int
    {
        return max(0, $this->remaining() - $this->openScheduledCount());
    }
}
