<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SummaryVideo extends Model
{
    use HasFactory;

    protected $fillable = ['video_id', 'summary', 'keywords_with_timestamps'];

    protected $casts = [
        'keywords_with_timestamps' => 'array',
    ];

    public function transcript()
    {
        return $this->belongsTo(TranscriptVideo::class, 'video_id');
    }
}
