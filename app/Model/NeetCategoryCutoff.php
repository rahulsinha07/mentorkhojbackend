<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class NeetCategoryCutoff extends Model
{
    protected $fillable = [
        'year',
        'category',
        'percentile',
        'cutoff_marks_low',
        'cutoff_marks_high',
        'source_note',
    ];
}
