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
        'fund_total',
        'fund_used',
        'platform_fee_percent',
        'notes',
        'granted_by_admin_id',
        'payment_status',
        'payment_confirmed_at',
        'payment_confirmed_by_admin_id',
    ];

    protected $casts = [
        'credits_total' => 'integer',
        'credits_used' => 'integer',
        'fund_total' => 'float',
        'fund_used' => 'float',
        'platform_fee_percent' => 'float',
        'payment_confirmed_at' => 'datetime',
    ];

    public function isPaymentConfirmed(): bool
    {
        return (string) ($this->payment_status ?? 'pending') === 'paid';
    }

    /** This pack's own fee rate if one was set at creation, else the global default. */
    public function effectiveFeePercent(): float
    {
        return $this->platform_fee_percent !== null
            ? (float) $this->platform_fee_percent
            : \App\CentralLogics\MentorLogic::platformFeePercent();
    }

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
            ->whereIn('booking_source', ['credit', 'session'])
            ->whereIn('status', ['assigned', 'requested', 'confirmed', 'reschedule_requested'])
            ->count();
    }

    public function availableToSchedule(): int
    {
        return (int) $this->bookings()
            ->whereIn('booking_source', ['credit', 'session'])
            ->where('status', 'assigned')
            ->count();
    }

    public function fundRemaining(): float
    {
        return round(max(0, (float) $this->fund_total - (float) $this->fund_used), 2);
    }

    public function remainingCreditsBeforeConsume(): int
    {
        return max(1, $this->remaining());
    }

    public function currentSessionGrossRate(): float
    {
        $remainingCredits = $this->remaining();
        if ($remainingCredits <= 0) {
            return 0.0;
        }

        return round($this->fundRemaining() / $remainingCredits, 2);
    }

    public function currentSessionNetRate(): float
    {
        $gross = $this->currentSessionGrossRate();
        if ($gross <= 0) {
            return 0.0;
        }

        $feePercent = $this->effectiveFeePercent();
        $fee = round($gross * ($feePercent / 100), 2);

        return round($gross - $fee, 2);
    }
}
