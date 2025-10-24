<?php

namespace App\Http\Controllers;

use App\Models\Mod;
use App\Models\ModVideo;
use App\Models\ModVideoReport;
use App\Services\YouTubeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class VideoController extends Controller
{
    protected YouTubeService $youtubeService;

    public function __construct(YouTubeService $youtubeService)
    {
        $this->youtubeService = $youtubeService;
    }

    /**
     * Submit a new video
     */
    public function submit(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Jelentkezz be a videó beküldéséhez.'], 401);
        }

        $request->validate([
            'mod_id' => 'required|exists:mods,id',
            'youtube_url' => 'required|url',
        ]);

        $user = Auth::user();
        $modId = $request->mod_id;

        // Rate limiting: 3 videos per day per user
        $key = 'video-submit:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'message' => 'Elérted a napi 3 videós limitedet. Próbáld újra holnap.',
            ], 429);
        }

        $youtubeId = $this->youtubeService->extractVideoId($request->youtube_url);

        if (!$youtubeId) {
            return response()->json(['message' => 'Érvénytelen YouTube URL.'], 400);
        }

        // Check for duplicate
        $existing = ModVideo::where('mod_id', $modId)
            ->where('youtube_id', $youtubeId)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Ez a videó már be lett küldve ehhez a modhoz.'], 409);
        }

        // Fetch video details from YouTube API
        $videoDetails = $this->youtubeService->fetchVideoDetails($youtubeId);

        // Download thumbnail
        $thumbnailData = $this->youtubeService->downloadThumbnail($youtubeId, $modId);

        // Get next position
        $nextPosition = ModVideo::where('mod_id', $modId)
            ->whereIn('status', ['approved', 'reported'])
            ->max('position') + 1;

        $video = ModVideo::create([
            'mod_id' => $modId,
            'submitted_by' => $user->id,
            'youtube_id' => $youtubeId,
            'youtube_url' => $request->youtube_url,
            'video_title' => $videoDetails['title'] ?? '',
            'video_description' => $videoDetails['description'] ?? '',
            'duration' => $videoDetails['duration'] ?? '',
            'thumbnail_path' => $thumbnailData['path'] ?? null,
            'status' => 'pending',
            'position' => $nextPosition,
        ]);

        RateLimiter::hit($key, 86400); // 24 hours

        return response()->json([
            'message' => 'Videó sikeresen beküldve! Moderálás után jelenik meg.',
            'video' => $video,
        ], 201);
    }

    /**
     * Report a video
     */
    public function report(Request $request, $videoId)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Jelentkezz be a videó jelentéséhez.'], 401);
        }

        $video = ModVideo::findOrFail($videoId);
        $user = Auth::user();

        // Check if already reported
        $existing = ModVideoReport::where('video_id', $videoId)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Már jelentetted ezt a videót.'], 409);
        }

        DB::transaction(function () use ($video, $user) {
            ModVideoReport::create([
                'video_id' => $video->id,
                'user_id' => $user->id,
            ]);

            $video->increment('report_count');

            // Auto-flag as reported if report count reaches threshold
            if ($video->report_count >= 3 && $video->status === 'approved') {
                $video->update(['status' => 'reported']);
            }
        });

        return response()->json([
            'message' => 'Köszönjük a jelentést! A moderátorok hamarosan átnézik.',
            'report_count' => $video->fresh()->report_count,
        ]);
    }

    /**
     * Delete/hide a video (for mod authors and moderators)
     */
    public function destroy($videoId)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Nincs jogosultságod ehhez a művelethez.'], 401);
        }

        $video = ModVideo::findOrFail($videoId);
        $user = Auth::user();
        $mod = $video->mod;

        // Check permissions
        $canManage = $user->is_admin || $user->id === $mod->user_id;

        if (!$canManage) {
            return response()->json(['message' => 'Nincs jogosultságod ehhez a művelethez.'], 403);
        }

        // For mod authors: delete the video
        // For others: just reject it
        if ($user->id === $mod->user_id) {
            $video->delete();
            $message = 'Videó törölve.';
        } else {
            $video->update(['status' => 'rejected']);
            $message = 'Videó elrejtve a galériából.';
        }

        return response()->json(['message' => $message]);
    }

    /**
     * Feature a video
     */
    public function feature($videoId)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Nincs jogosultságod ehhez a művelethez.'], 401);
        }

        $video = ModVideo::findOrFail($videoId);
        $user = Auth::user();
        $mod = $video->mod;

        $canManage = $user->is_admin || $user->id === $mod->user_id;

        if (!$canManage) {
            return response()->json(['message' => 'Nincs jogosultságod ehhez a művelethez.'], 403);
        }

        DB::transaction(function () use ($video, $mod) {
            // Unfeature all other videos for this mod
            ModVideo::where('mod_id', $mod->id)
                ->where('id', '!=', $video->id)
                ->update(['is_featured' => false, 'featured_at' => null]);

            // Feature this video
            $video->update([
                'is_featured' => true,
                'featured_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Kiemelt videó frissítve.']);
    }

    /**
     * Unfeature a video
     */
    public function unfeature($videoId)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Nincs jogosultságod ehhez a művelethez.'], 401);
        }

        $video = ModVideo::findOrFail($videoId);
        $user = Auth::user();
        $mod = $video->mod;

        $canManage = $user->is_admin || $user->id === $mod->user_id;

        if (!$canManage) {
            return response()->json(['message' => 'Nincs jogosultságod ehhez a művelethez.'], 403);
        }

        $video->update([
            'is_featured' => false,
            'featured_at' => null,
        ]);

        return response()->json(['message' => 'A videó már nem kiemelt.']);
    }
}
