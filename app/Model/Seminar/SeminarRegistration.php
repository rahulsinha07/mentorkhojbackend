<?php

namespace App\Model\Seminar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeminarRegistration extends Model
{
    protected $table = 'seminar_registrations';

    protected $fillable = [
        'seminar_id',
        'registration_id',
        'name',
        'email',
        'phone',
        'college',
        'details',
        'source',
        'status',
    ];

    public function seminar(): BelongsTo
    {
        return $this->belongsTo(Seminar::class);
    }
}
