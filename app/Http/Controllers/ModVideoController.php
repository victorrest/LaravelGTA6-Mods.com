<?php

namespace App\Http\Controllers;

use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModVideo;
use App\Services\YouTubeImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ModVideoController extends Controller
{
    public function __construct(protected YouTubeImporter $youTubeImporter)
    {
    }

    public function store(Request $request, ModCategory $category, Mod $mod): RedirectResponse
    {
        abort_unless($mod->categories->contains($category), 404);

        $data = $request->validate([
            'video_url' => ['required', 'url'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $videoId = $this->extractYouTubeId($data['video_url']);

        if (! $videoId) {
            return back()->withErrors(['video_url' => 'Nem sikerült meghatározni a YouTube videó azonosítóját.'])->withInput();
        }

        $meta = $this->youTubeImporter->fetchVideoMeta($videoId);

        if (! $meta) {
            return back()->withErrors(['video_url' => 'Nem sikerült beolvasni a YouTube meta adatokat. Ellenőrizd az API kulcsot.'])->withInput();
        }

        $thumbnailPath = $this->youTubeImporter->downloadThumbnail($meta['thumbnail_url'] ?? '', $videoId);

        try {
            ModVideo::create([
                'mod_id' => $mod->getKey(),
                'user_id' => Auth::id(),
                'platform' => 'youtube',
                'external_id' => $videoId,
                'title' => $meta['title'] ?? 'YouTube videó',
                'thumbnail_path' => $thumbnailPath,
                'duration' => $meta['duration'] ?? null,
                'channel_title' => $meta['channel_title'] ?? null,
                'payload' => [
                    'raw_url' => $data['video_url'],
                    'note' => $data['note'] ?? null,
                    'meta' => $meta,
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Unable to create mod video submission', [
                'mod_id' => $mod->getKey(),
                'video_id' => $videoId,
                'exception' => $exception,
            ]);

            return back()->withErrors(['video_url' => 'A videó rögzítése közben hiba történt.'])->withInput();
        }

        return back()->with('status', 'A videó beküldése sikerült, hamarosan moderációra kerül.');
    }

    protected function extractYouTubeId(string $url): ?string
    {
        $patterns = [
            '/youtu.be\/(.+?)(?:\?|$)/',
            '/youtube.com\/(?:watch\?v=|embed\/|shorts\/)([^&?#\n]+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return Str::before($matches[1], '?');
            }
        }

        if (preg_match('/^[\w-]{11}$/', $url)) {
            return $url;
        }

        return null;
    }
}
