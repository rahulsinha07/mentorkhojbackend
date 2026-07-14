<?php

namespace App\Model\Internship;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternshipApplication extends Model
{
    protected $table = 'internship_applications';

    protected $fillable = [
        'internship_id',
        'application_id',
        'name',
        'email',
        'phone',
        'org',
        'role',
        'resume_url',
        'message',
        'source',
        'status',
    ];

    public function internship(): BelongsTo
    {
        return $this->belongsTo(Internship::class);
    }
}
