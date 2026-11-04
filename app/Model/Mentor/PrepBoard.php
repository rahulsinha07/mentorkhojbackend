<?php

namespace App\Model\Mentor;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrepBoard extends Model
{
    protected $table = 'prep_boards';

    protected $fillable = [
        'mentor_id',
        'mentee_user_id',
        'status',
        'exam_category',
        'mentor_last_edited_at',
        'plan_ready_notified_at',
        'last_update_notified_at',
    ];

    protected $casts = [
        'mentor_last_edited_at' => 'datetime',
        'plan_ready_notified_at' => 'datetime',
        'last_update_notified_at' => 'datetime',
    ];

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(Mentor::class, 'mentor_id');
    }

    public function mentee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentee_user_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(PrepBoardSubject::class, 'prep_board_id')
            ->orderBy('slot')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(PrepBoardUpdate::class, 'prep_board_id')
            ->orderByDesc('entry_date')
            ->orderByDesc('id');
    }

    public function targetCycles(): HasMany
    {
        return $this->hasMany(PrepBoardTargetCycle::class, 'prep_board_id')
            ->orderByDesc('start_date');
    }

    public function dailyTargets(): HasMany
    {
        return $this->hasMany(PrepBoardDailyTarget::class, 'prep_board_id')
            ->orderByDesc('target_date');
    }
}
