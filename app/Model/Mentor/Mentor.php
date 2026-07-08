<?php

namespace App\Model\Mentor;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Mentor extends Model
{
    protected $table = 'mentors';

    protected $casts = [
        'is_published' => 'boolean',
        'profile_discount' => 'float',
        'view_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'legacy_product_id',
        'username',
        'display_name',
        'headline',
        'bio_html',
        'images',
        'category_ids',
        'status',
        'is_published',
        'profile_discount',
        'discount_type',
        'view_count',
        'share_caption',
        'share_short_url',
        'social_links',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(MentorService::class)->orderBy('sort_order');
    }

    public function enabledServices(): HasMany
    {
        return $this->hasMany(MentorService::class)
            ->where('is_enabled', true)
            ->orderBy('sort_order');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(MentorBooking::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(MentorEarning::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(MentorPayout::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(MentorSetting::class);
    }

    public function shareLogs(): HasMany
    {
        return $this->hasMany(MentorShareLog::class);
    }

    public function getImagesArrayAttribute(): array
    {
        if (empty($this->images)) {
            return [];
        }
        $decoded = json_decode($this->images, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getCategoryIdsArrayAttribute(): array
    {
        if (empty($this->category_ids)) {
            return [];
        }
        $decoded = json_decode($this->category_ids, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getSocialLinksArrayAttribute(): array
    {
        if (empty($this->social_links)) {
            return [];
        }
        $decoded = json_decode($this->social_links, true);
        return is_array($decoded) ? array_filter($decoded) : [];
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)->where('status', 'active');
    }
}
