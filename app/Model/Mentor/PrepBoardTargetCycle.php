<?php

namespace App\Model\Mentor;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrepBoardTargetCycle extends Model
{
    protected $table = 'prep_board_target_cycles';

    protected $fillable = [
        'prep_board_id',
        'start_date',
        'days',
        'status',
        'published_at',
        'publish_notified_at',
        'assigned_by_user_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'days' => 'array',
        'published_at' => 'datetime',
        'publish_notified_at' => 'datetime',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(PrepBoard::class, 'prep_board_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function dailyTargets(): HasMany
    {
        return $this->hasMany(PrepBoardDailyTarget::class, 'cycle_id');
    }
}
