<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class YouTubeImporter
{
    public function fetchVideoMeta(string $videoId): ?array
    {
        $apiKey = Setting::get('youtube.api_key') ?? config('services.youtube.key');

        if (! $apiKey) {
            return null;
        }

        try {
            $response = Http::get('https://www.googleapis.com/youtube/v3/videos', [
                'id' => $videoId,
                'part' => 'snippet,contentDetails',
                'key' => $apiKey,
            ])->throw()->json();
        } catch (RequestException $exception) {
            Log::warning('Failed to fetch YouTube metadata', [
                'video_id' => $videoId,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        $item = $response['items'][0] ?? null;

        if (! $item) {
            return null;
        }

        $snippet = $item['snippet'] ?? [];
        $details = $item['contentDetails'] ?? [];

        return [
            'title' => $snippet['title'] ?? 'YouTube Video',
            'description' => $snippet['description'] ?? '',
            'channel_title' => $snippet['channelTitle'] ?? null,
            'thumbnail_url' => $this->resolveThumbnailUrl($snippet['thumbnails'] ?? []),
            'duration' => $details['duration'] ?? null,
            'published_at' => $snippet['publishedAt'] ?? null,
        ];
    }

    public function downloadThumbnail(string $thumbnailUrl, string $videoId): ?string
    {
        if (! $thumbnailUrl) {
            return null;
        }

        try {
            $response = Http::get($thumbnailUrl);
        } catch (RequestException $exception) {
            Log::warning('Failed to fetch YouTube thumbnail', [
                'video_id' => $videoId,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $extension = 'jpg';
        $path = 'mod-videos/' . $videoId . '-' . Str::random(8) . '.' . $extension;
        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    protected function resolveThumbnailUrl(array $thumbnails): ?string
    {
        foreach (['maxres', 'standard', 'high', 'medium', 'default'] as $key) {
            if (! empty($thumbnails[$key]['url'])) {
                return $thumbnails[$key]['url'];
            }
        }

        return null;
    }
}
