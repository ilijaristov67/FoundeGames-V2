<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\TranscriptVideo;
use App\Models\SummaryVideo;
use App\Http\Resources\TranscriptVideoResource;
use App\Http\Resources\VideoResource;

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

        $transcript = $this->fetchTranscript($videoId);
        if (!$transcript || !is_array($transcript) || empty($transcript) || !is_array($transcript[0])) {
            return response()->json(['error' => 'Transcript data is not in the expected format'], 500);
        }

        $summary = $this->generateSummary($transcript);
        if (!$summary) {
            return response()->json(['error' => 'Failed to summarize transcript'], 500);
        }

        $keywordsWithTimestamps = $this->extractKeywordsWithTimestamps($transcript);
        if (!$keywordsWithTimestamps) {
            return response()->json(['error' => 'Failed to extract keywords'], 500);
        }

        $transcriptRecord = TranscriptVideo::create([
            'video_id' => $videoId,
            'video_url' => $videoUrl,
            'transcript' => json_encode($transcript),
        ]);
        SummaryVideo::create([
            'video_id' => $transcriptRecord->id,
            'summary' => $summary,
            'keywords_with_timestamps' => json_encode($keywordsWithTimestamps),
        ]);


        return new TranscriptVideoResource($transcriptRecord);
    }
    private function extractVideoId($videoUrl)
    {
        preg_match('/(?:v=|\/)([a-zA-Z0-9_-]{11})/', $videoUrl, $matches);
        if (!isset($matches[1])) {

            Log::error('Failed to extract video ID from URL: ' . $videoUrl);
            return null;
        }
        return $matches[1];
    }

    private function fetchTranscript($videoId)
    {
        $output = [];
        $return_var = 0;
        $command = sprintf("python %s %s", escapeshellarg(base_path('get_transcript.py')), escapeshellarg($videoId));
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            return null;
        }

        return json_decode(implode("\n", $output), true);
    }

    private function generateSummary(array $transcript)
    {
        $transcriptText = implode(" ", array_map(function ($t) {
            return isset($t['start'], $t['text']) ? "[{$t['start']}] {$t['text']}" : '';
        }, $transcript));

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'Summarize this episode of Founder Games by focusing on the interactions and conversations between the characters, with minimal emphasis on brands or company details. Use character names from the episode and highlight key moments of dialogue, contrasting opinions, or collaborative efforts. The summary should begin with a title in <h3> tags and be at least 50 words but no more than 300 words. Make the summary length appropriate to the video duration, and if possible, use specific insights from the transcript to accurately represent the character dynamics. Avoid brand emphasis and focus on personal stories, decisions, or reactions among the characters.'],

                ['role' => 'user', 'content' => $transcriptText],
            ],
            'max_tokens' => 500,
            'temperature' => 0.5,
        ]);

        return $response->successful() ? $response->json('choices')[0]['message']['content'] : null;
    }

    private function extractKeywordsWithTimestamps(array $transcript)
    {
        $transcriptText = implode(" ", array_map(function ($t) {
            return isset($t['start'], $t['text']) ? "[{$t['start']}] {$t['text']}" : '';
        }, $transcript));

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'Extract the main keywords or topics from the transcript, with special emphasis on terms and phrases related to entrepreneurship, marketing strategies, cost analysis, business investments, and valuable advice or insights. Include character names, startup names, important gestures, specific business advice, and any mention of partnerships or funding. Format the response as a JSON object where each keyword is associated with its closest timestamp. Use the format: {"keyword": ["timestamp1", "timestamp2", ...]}. Focus on keywords that would be of interest to business professionals or investors, such as business models, revenue streams, growth strategies, market analysis, challenges faced, key decisions, and any other actionable insights.'],
                ['role' => 'user', 'content' => $transcriptText],
            ],
            'max_tokens' => 500,
            'temperature' => 0.5,
        ]);

        $keywordsData = $response->successful() ? $response->json('choices')[0]['message']['content'] : null;

        return $keywordsData ? json_decode($keywordsData, true) : null;
    }

    public function check()
    {
        return response()->json([
            'success' => true,
            'message' => 'successfull check'
        ]);
    }

    public function show($id)
    {
        $video = TranscriptVideo::with('summary')->findOrFail($id);
        return new VideoResource($video);
    }
}
