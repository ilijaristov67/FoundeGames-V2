<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\SummaryVideoResource;

class VideoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'video_id' => $this->video_id,
            'video_url' => $this->video_url,
            'transcript' => $this->transcript,
            'summary' => new SummaryVideoResource($this->summary),
        ];
    }
}
