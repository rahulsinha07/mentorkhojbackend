<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class StudentSectionOverride extends Model
{
    protected $table = 'student_section_overrides';

    protected $fillable = [
        'phone_digits',
        'email',
        'user_id',
        'section',
        'message_template',
    ];
}
