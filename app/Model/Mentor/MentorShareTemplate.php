<?php

namespace App\Model\Mentor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MentorShareTemplate extends Model
{
    protected $table = 'mentor_share_templates';

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $fillable = [
        'slug',
        'title',
        'subtitle',
        'poster_image',
        'default_caption',
        'hashtags',
        'is_active',
        'sort_order',
    ];

    public function shareLogs(): HasMany
    {
        return $this->hasMany(MentorShareLog::class, 'template_id');
    }

    public function getHashtagsArrayAttribute(): array
    {
        if (empty($this->hashtags)) {
            return [];
        }
        $decoded = json_decode($this->hashtags, true);
        return is_array($decoded) ? $decoded : [];
    }
}
