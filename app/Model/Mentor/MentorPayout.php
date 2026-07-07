<?php

namespace App\Model\Mentor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorPayout extends Model
{
    protected $table = 'mentor_payouts';

    protected $casts = [
        'amount' => 'float',
    ];

    protected $fillable = [
        'mentor_id',
        'amount',
        'method',
        'bank_details',
        'status',
    ];

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(Mentor::class);
    }
}
