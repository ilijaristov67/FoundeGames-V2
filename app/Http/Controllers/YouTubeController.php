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
        preg_match('/[?&]v=([^&]+)/', $videoUrl, $matches);
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
                ['role' => 'system', 'content' => 'Summarize this episode, with a focus on the key interactions, conversations, and dynamics between characters, prioritizing aspects like investments, marketing strategies, cost analysis, body language, and mentorship. Begin the summary with a title in <h3> tags and include specific character names from the episode to highlight pivotal moments of dialogue. Capture how differing perspectives on business strategies and entrepreneurship spark new ideas or decisions. Emphasize the authenticity of the interactions and mentorship exchange, showing how opinions on topics like startup investments, marketing approaches, and financial planning lead to breakthroughs or challenges. Avoid mentioning company names or products; instead, detail the essence of the discussions and insights shared by the characters. The summary should range from 50 to 300 words, with the length adjusted based on the video duration. and put the title separately so I can fetch it on frotnend'],

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

    public function search(Request $request, $id)
    {
        $request->validate([
            'question' => 'required|string',
        ]);
        $question = $request->input('question');

        $transcriptRow = TranscriptVideo::findOrFail($id);

        $transcriptText = $transcriptRow->transcript;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are provided with a question and a transcript of a YouTube video. Please analyze the transcript and extract the specific parts where the question is directly or indirectly addressed. If possible, include surrounding context to capture the complete answer, especially if it spans multiple lines. Please format the output as a list of transcript excerpts, including timestamps if available, return it in json format'
                ],
                ['role' => 'user', 'content' => "Question: {$question}\n\nTranscript: {$transcriptText}"],
            ],
            'max_tokens' => 500,
            'temperature' => 0.2,
        ]);
        return $response->successful() ? $response->json('choices')[0]['message']['content'] : null;
    }

    public function searchAll(Request $request)
    {
        $request->validate([
            'question' => 'required|string',
        ]);

        $question = $request->input('question');
        $videos = TranscriptVideo::all();
        $results = [];

        foreach ($videos as $video) {
            $transcriptText = $video->transcript;
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are provided with a question and a transcript of a YouTube video. Please analyze the transcript and extract the specific parts where the question is directly addressed, including surrounding context to capture the complete answer, especially if it spans multiple lines. Only return answers where the question is directly mentioned. Format the output as a JSON object with "answer" for the answer text and "timestamp" for the start time of the answer. If the question is not discussed in the transcript, ignore this video and do not include it in the response.'
                    ],
                    ['role' => 'user', 'content' => "Question: {$question}\n\nTranscript: {$transcriptText}"],
                ],
                'max_tokens' => 500,
                'temperature' => 0.2,
            ]);

            if ($response->successful()) {
                $result = $response->json('choices')[0]['message']['content'];
                $results[] = [
                    'video_id' => $video->id,
                    'video_url' => $video->video_url,
                    'result' => json_decode($result, true),
                ];
            } else {
                $results[] = [
                    'video_id' => $video->id,
                    'video_url' => $video->video_url,
                    'result' => ['answer' => 'error retrieving data', 'timestamp' => null],
                ];
            }
        }

        return response()->json($results);
    }
    public function getVideos()
    {
        $videos = TranscriptVideo::with('summary')->get();
        return VideoResource::collection($videos);
    }
}
