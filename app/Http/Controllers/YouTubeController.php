<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TranscriptVideo;


class YouTubeController extends Controller
{
    public function index()
    {
        return view('youtube');
    }

    public function getTranscript(Request $request)
    {
        $request->validate([
            'video_url' => 'required|url',
        ]);

        $videoUrl = $request->input('video_url');
        $videoId = $this->extractVideoId($videoUrl);

        if (!$videoId) {
            return response()->json(['error' => 'Invalid YouTube URL'], 400);
        }

        $output = [];
        $return_var = 0;
        $videoId = escapeshellarg($videoId);
        $pythonPath = 'python';
        $scriptPath = escapeshellarg(base_path('get_transcript.py'));
        $command = "$pythonPath $scriptPath $videoId";
        exec($command, $output, $return_var);
        if ($return_var !== 0) {
            return response()->json(['error' => 'Unable to fetch transcript'], 500);
        }

        $transcript = json_decode(implode("\n", $output), true);
        $transcriptText = implode(" ", array_map(fn($t) => "[{$t['start']}] {$t['text']}", $transcript));

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'Please analyze the transcript below and provide a JSON structure with the following format:
        {
        "intervals": [
        {
            "start": "timestamp_start",
            "end": "timestamp_end",
            "keywords": ["keyword1", "keyword2", "keyword3"],
            "summary": "Brief summary of the main points discussed in this interval."
        },
        ...
        ]
        }
        Requirements:
        - Divide the transcript into intervals of approximately 30 seconds each, based on the timestamps in the text.
        - For each interval, extract the most important keywords or phrases, focusing on nouns and key concepts.
        - Include a brief summary (one sentence) that describes the primary focus or topic of each interval.
        - Ensure that each interval in the JSON object contains a `start` timestamp, an `end` timestamp, a list of `keywords`, and a `summary`.
        '],
                ['role' => 'user', 'content' => $transcriptText],
            ],
            'max_tokens' => 500,
            'temperature' => 0.5,
        ]);
        if ($response->successful()) {
            $summary = $response->json('choices')[0]['message']['content'];
            return response()->json(['summary' => $summary]);
        } else {

            Log::error('OpenAI API Error:', $response->json());
            return response()->json(['error' => 'Failed to summarize transcript'], 500);
        }
        return view('transcript', ['transcript' => $transcript]);
    }

    private function extractVideoId($videoUrl)
    {
        preg_match('/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?|watch|.+\/\S+\/|.*[?&]v=)|(?:v|e(?:mbed)?|watch)\?v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $videoUrl, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
}
