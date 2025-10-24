<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class YouTubeService
{
    /**
     * Extract YouTube video ID from URL
     */
    public function extractVideoId(string $url): ?string
    {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/';

        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Fetch video details from YouTube Data API v3
     */
    public function fetchVideoDetails(string $youtubeId): ?array
    {
        $apiKey = Setting::get('youtube_api_key');

        if (!$apiKey) {
            return null;
        }

        try {
            $response = Http::get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'snippet,contentDetails',
                'id' => $youtubeId,
                'key' => $apiKey,
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();

            if (empty($data['items'][0])) {
                return null;
            }

            $item = $data['items'][0];
            $snippet = $item['snippet'] ?? [];
            $contentDetails = $item['contentDetails'] ?? [];

            return [
                'title' => $snippet['title'] ?? '',
                'description' => $snippet['description'] ?? '',
                'duration' => $contentDetails['duration'] ?? '',
            ];
        } catch (\Exception $e) {
            \Log::error('YouTube API error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Download YouTube thumbnail and save to storage
     */
    public function downloadThumbnail(string $youtubeId, int $modId = 0): ?array
    {
        $sizes = ['maxresdefault', 'sddefault', 'hqdefault'];
        $sourceUrl = null;

        foreach ($sizes as $size) {
            $url = "https://i.ytimg.com/vi/{$youtubeId}/{$size}.jpg";

            try {
                $response = Http::head($url);
                if ($response->successful()) {
                    $sourceUrl = $url;
                    break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        if (!$sourceUrl) {
            return null;
        }

        try {
            $response = Http::get($sourceUrl);

            if (!$response->successful()) {
                return null;
            }

            $filename = 'thumbnails/' . $youtubeId . '-' . Str::random(8) . '.jpg';
            Storage::disk('public')->put($filename, $response->body());

            return [
                'path' => $filename,
                'url' => Storage::disk('public')->url($filename),
            ];
        } catch (\Exception $e) {
            \Log::error('Thumbnail download error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get thumbnail URL (from storage or YouTube)
     */
    public function getThumbnailUrl(string $youtubeId, ?string $storedPath = null, string $quality = 'maxresdefault'): string
    {
        if ($storedPath && Storage::disk('public')->exists($storedPath)) {
            return Storage::disk('public')->url($storedPath);
        }

        return "https://i.ytimg.com/vi/{$youtubeId}/{$quality}.jpg";
    }

    /**
     * Validate YouTube URL
     */
    public function isValidUrl(string $url): bool
    {
        return $this->extractVideoId($url) !== null;
    }
}
