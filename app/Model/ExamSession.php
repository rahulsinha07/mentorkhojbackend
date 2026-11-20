<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ExamSession extends Model
{
    protected $fillable = [
        'exam_type',
        'year',
        'session',
        'total_candidates',
        'source_note',
    ];

    public function scopeExamType($query, string $examType)
    {
        return $query->where('exam_type', $examType);
    }
}
