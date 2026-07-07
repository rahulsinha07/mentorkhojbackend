<?php

namespace App\Model\Mentor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorSetting extends Model
{
    protected $table = 'mentor_settings';

    protected $fillable = [
        'mentor_id',
        'availability_json',
        'notification_prefs',
        'payout_details',
        'share_prefs',
    ];

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(Mentor::class);
    }
}
