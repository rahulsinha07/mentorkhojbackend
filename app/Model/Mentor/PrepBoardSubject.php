<?php

namespace App\Model\Mentor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrepBoardSubject extends Model
{
    protected $table = 'prep_board_subjects';

    protected $fillable = [
        'prep_board_id',
        'slot',
        'subject_name',
        'chapters',
        'sort_order',
        'theory_text',
        'practice_text',
        'note',
        'hours_devoted',
        'updated_by_user_id',
    ];

    protected $casts = [
        'hours_devoted' => 'float',
        'sort_order' => 'integer',
        'chapters' => 'array',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(PrepBoard::class, 'prep_board_id');
    }
}
