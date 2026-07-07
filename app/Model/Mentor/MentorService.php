<?php

namespace App\Model\Mentor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MentorService extends Model
{
    protected $table = 'mentor_services';

    protected $casts = [
        'price' => 'float',
        'compare_at_price' => 'float',
        'duration_minutes' => 'integer',
        'is_enabled' => 'boolean',
        'is_popular' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $fillable = [
        'mentor_id',
        'title',
        'description',
        'duration_minutes',
        'price',
        'compare_at_price',
        'badge',
        'is_enabled',
        'is_popular',
        'sort_order',
        'meeting_type',
    ];

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(Mentor::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(MentorBooking::class);
    }
}
