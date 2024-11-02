<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranscriptVideo extends Model
{
    protected $fillable = [
        'video_url',
        'transcript',
        'summary'
    ];
}
