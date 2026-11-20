<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class CollegeCutoff extends Model
{
    protected $fillable = [
        'exam_type',
        'year',
        'round',
        'institute_type',
        'institute_name',
        'program',
        'quota',
        'category',
        'is_pwd',
        'gender',
        'opening_rank',
        'closing_rank',
        'is_preparatory',
    ];

    protected $casts = [
        'is_pwd' => 'boolean',
        'is_preparatory' => 'boolean',
    ];

    public function scopeExamType($query, string $examType)
    {
        return $query->where('exam_type', $examType);
    }

    public function scopeLatestBatch($query, string $examType)
    {
        $latest = static::examType($examType)
            ->orderByDesc('year')
            ->orderByDesc('round')
            ->first(['year', 'round']);

        if (!$latest) {
            return $query->whereRaw('1 = 0');
        }

        return $query->examType($examType)->where('year', $latest->year)->where('round', $latest->round);
    }
}
