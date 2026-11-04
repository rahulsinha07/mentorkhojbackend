<?php

namespace App\Model\Mentor;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrepBoardDailyTarget extends Model
{
    protected $table = 'prep_board_daily_targets';

    protected $fillable = [
        'prep_board_id',
        'target_date',
        'cycle_id',
        'day_offset',
        'items',
        'source',
        'assigned_by_user_id',
    ];

    protected $casts = [
        'target_date' => 'date',
        'items' => 'array',
        'day_offset' => 'integer',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(PrepBoard::class, 'prep_board_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PrepBoardTargetCycle::class, 'cycle_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
