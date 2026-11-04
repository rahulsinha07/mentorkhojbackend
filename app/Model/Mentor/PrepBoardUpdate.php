<?php

namespace App\Model\Mentor;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrepBoardUpdate extends Model
{
    protected $table = 'prep_board_updates';

    protected $fillable = [
        'prep_board_id',
        'entry_date',
        'body',
        'target_progress',
        'reply_body',
        'replied_at',
        'replied_by_user_id',
        'author_user_id',
        'author_role',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'target_progress' => 'array',
        'replied_at' => 'datetime',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(PrepBoard::class, 'prep_board_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by_user_id');
    }
}
