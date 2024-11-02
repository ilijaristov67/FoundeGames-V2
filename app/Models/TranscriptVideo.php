<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TranscriptVideo extends Model
{
    use HasFactory;

    protected $fillable = ['video_id', 'video_url', 'transcript'];

    public function summary()
    {
        return $this->hasOne(SummaryVideo::class, 'video_id');
    }
}
