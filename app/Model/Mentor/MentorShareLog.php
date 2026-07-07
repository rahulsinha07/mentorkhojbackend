<?php

namespace App\Model\Mentor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorShareLog extends Model
{
    protected $table = 'mentor_share_logs';

    protected $fillable = [
        'mentor_id',
        'template_id',
        'channel',
        'profile_url',
    ];

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(Mentor::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MentorShareTemplate::class, 'template_id');
    }
}
