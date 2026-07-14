<?php

namespace App\Model\Internship;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Internship extends Model
{
    protected $table = 'internships';

    protected $casts = [
        'skills' => 'array',
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $fillable = [
        'slug',
        'role',
        'team',
        'location',
        'type',
        'duration',
        'stipend',
        'blurb',
        'skills',
        'status',
        'is_published',
        'sort_order',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(InternshipApplication::class)->latest();
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)->where('status', '!=', 'draft');
    }

    public function scopeAcceptingApplications($query)
    {
        return $query->published()->where('status', 'active');
    }
}
