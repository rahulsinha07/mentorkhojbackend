<?php

namespace App\Model\Mentor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorDocumentFile extends Model
{
    protected $table = 'mentor_document_files';

    protected $casts = [
        'size' => 'integer',
        'sort_order' => 'integer',
    ];

    protected $fillable = [
        'mentor_document_id',
        'file_path',
        'original_name',
        'mime',
        'size',
        'sort_order',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(MentorDocument::class, 'mentor_document_id');
    }
}
