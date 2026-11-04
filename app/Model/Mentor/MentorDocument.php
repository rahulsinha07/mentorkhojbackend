<?php

namespace App\Model\Mentor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MentorDocument extends Model
{
    protected $table = 'mentor_documents';

    protected $casts = [
        'price' => 'float',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
        'total_size_bytes' => 'integer',
    ];

    protected $fillable = [
        'mentor_id',
        'title',
        'description',
        'price',
        'is_enabled',
        'sort_order',
        'total_size_bytes',
    ];

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(Mentor::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(MentorDocumentFile::class)->orderBy('sort_order');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(MentorDocumentPurchase::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }
}
