<?php

namespace App\Model\Seminar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seminar extends Model
{
    protected $table = 'seminars';

    protected $casts = [
        'highlights' => 'array',
        'is_published' => 'boolean',
        'sort_order' => 'integer',
        'fee_amount' => 'decimal:2',
    ];

    protected $fillable = [
        'slug',
        'title',
        'tagline',
        'blurb',
        'date',
        'mode',
        'duration',
        'audience',
        'emoji',
        'highlights',
        'status',
        'is_published',
        'sort_order',
        'fee_amount',
        'currency',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(SeminarBooking::class)->latest();
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(SeminarRegistration::class)->latest();
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)->where('status', '!=', 'draft');
    }

    public function scopeAcceptingRegistrations($query)
    {
        return $query->published()->where('status', 'active');
    }
}
