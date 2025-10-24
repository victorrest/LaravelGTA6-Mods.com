<?php

namespace App\Http\Controllers;

use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModComment;
use App\Models\ModLike;
use App\Models\UserActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ModController extends Controller
{
    public function index(Request $request)
    {
        $query = Mod::query()->published()->with(['author', 'categories']);

        if ($categorySlug = $request->string('category')->toString()) {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $categorySlug));
        }

        $sort = $request->string('sort')->toString() ?: 'latest';

        match ($sort) {
            'popular' => $query->orderByDesc('downloads')->orderByDesc('likes'),
            'rating' => $query->orderByDesc('rating'),
            default => $query->orderByDesc('published_at'),
        };

        $mods = $query->paginate(12);

        return view('mods.index', [
            'mods' => $mods,
            'categories' => ModCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function show(ModCategory $category, Mod $mod)
    {
        abort_unless($mod->status === 'published', Response::HTTP_NOT_FOUND);

        // Verify that the mod belongs to this category
        abort_unless($mod->categories->contains($category), Response::HTTP_NOT_FOUND);

        $mod->loadMissing(['author', 'categories', 'galleryImages'])->loadCount('comments');

        // Load approved videos
        $videos = $mod->videos()
            ->approved()
            ->with('submitter')
            ->orderBy('is_featured', 'desc')
            ->orderBy('position')
            ->get();

        // Load mod versions
        $versions = $mod->versions()
            ->approved()
            ->orderByDesc('created_at')
            ->with('submitter')
            ->get();

        // Get current version (latest approved or the mod's own version)
        $currentVersion = $versions->where('is_current', true)->first() ?? $versions->first();

        $comments = $mod->comments()
            ->approved()
            ->whereNull('parent_id')
            ->with(['author', 'replies.author'])
            ->orderByDesc('likes_count')
            ->orderByDesc('created_at')
            ->take(15)
            ->get();

        $userRating = Auth::check()
            ? $mod->ratings()->where('user_id', Auth::id())->value('rating')
            : null;

        $relatedMods = Mod::query()
            ->published()
            ->whereKeyNot($mod->getKey())
            ->whereHas('categories', fn ($q) => $q->whereIn('mod_categories.id', $mod->categories->pluck('id')))
            ->with(['author', 'categories'])
            ->limit(4)
            ->get();

        $breadcrumbs = [
            [
                'label' => 'Home',
                'url' => route('home'),
                'is_home' => true,
            ],
        ];

        $primaryCategory = $mod->categories->first();

        if ($primaryCategory) {
            $breadcrumbs[] = [
                'label' => $primaryCategory->name,
                'url' => route('mods.index', ['category' => $primaryCategory->slug]),
            ];
        }

        $breadcrumbs[] = [
            'label' => $mod->title,
            'url' => route('mods.show', [$category, $mod]),
        ];

        $ratingValue = $mod->ratings_count > 0 ? (float) $mod->rating : null;
        $ratingFullStars = $ratingValue ? (int) floor($ratingValue) : 0;
        $ratingHasHalf = $ratingValue ? ($ratingValue - $ratingFullStars) >= 0.5 : false;

        $metaDetails = [
            'version' => $mod->version,
            'file_size' => $mod->file_size_label,
            'uploaded_at' => optional($mod->published_at)->format('M d, Y'),
            'updated_at' => optional($mod->updated_at)->format('M d, Y'),
        ];

        $galleryItems = [];
        $imageSequence = 1;

        $baseImage = [
            'type' => 'image',
            'src' => $mod->hero_image_url,
            'thumbnail_small' => $mod->hero_image_url,
            'thumbnail_large' => $mod->hero_image_url,
            'width' => 1920,
            'height' => 1080,
            'alt' => $mod->title,
            'title' => $mod->title,
            'identifier' => 'hero-image',
            'sequence' => $imageSequence++,
        ];

        $imageItems = collect($mod->galleryImages)
            ->map(function ($image) use (&$imageSequence, $mod) {
                $src = $image->url;

                return [
                    'type' => 'image',
                    'src' => $src,
                    'thumbnail_small' => $src,
                    'thumbnail_large' => $src,
                    'width' => 1920,
                    'height' => 1080,
                    'alt' => $image->caption ?: $mod->title,
                    'title' => $mod->title,
                    'identifier' => 'gallery-image-' . $imageSequence,
                    'sequence' => $imageSequence++,
                ];
            })
            ->filter(fn ($item) => !empty($item['src']))
            ->values();

        $imageItems->prepend($baseImage);

        $imageItems = $imageItems->unique('src')->values();

        $videoItems = $videos->map(function ($video) use (&$imageSequence, $mod) {
            $thumbnailLarge = $video->thumbnail_large_url;
            $thumbnailSmall = $video->thumbnail_small_url;

            return [
                'type' => 'video',
                'src' => $thumbnailLarge,
                'thumbnail_small' => $thumbnailSmall,
                'thumbnail_large' => $thumbnailLarge,
                'width' => 1920,
                'height' => 1080,
                'alt' => $video->video_title ?: $mod->title,
                'title' => $video->video_title ?: $mod->title,
                'identifier' => 'video-' . $video->id,
                'sequence' => $imageSequence++,
                'is_featured' => (bool) $video->is_featured,
                'youtube_id' => $video->youtube_id,
                'video_id' => $video->id,
                'submitter_name' => optional($video->submitter)->name ?? 'Community member',
                'profile_url' => optional($video->submitter)
                    ? route('author.profile', optional($video->submitter)->username ?? optional($video->submitter)->id)
                    : null,
                'status' => $video->status,
                'is_reported' => $video->status === 'reported',
                'report_count' => $video->report_count,
            ];
        })->values();

        $featuredVideo = $videoItems->firstWhere('is_featured', true);

        if ($featuredVideo) {
            $galleryItems[] = $featuredVideo;
            $galleryItems = array_merge($galleryItems, $imageItems->toArray());
            $galleryItems = array_merge($galleryItems, $videoItems->reject(fn ($item) => $item['is_featured'])->toArray());
        } else {
            $galleryItems = array_merge($imageItems->toArray(), $videoItems->toArray());
        }

        if (empty($galleryItems)) {
            $galleryItems[] = $baseImage;
        }

        $defaultImage = collect($galleryItems)
            ->first(fn ($item) => $item['type'] === 'image');

        $galleryJson = json_encode($galleryItems);

        $authUser = Auth::user();
        $canManagePin = $authUser && $authUser->getKey() === $mod->user_id;
        $isPinnedByOwner = $canManagePin && (int) $authUser->pinned_mod_id === (int) $mod->id;

        // Check if user can manage this mod
        $canManageMod = Auth::check() && (Auth::id() === $mod->user_id || Auth::user()->is_admin);
        $isPendingMod = $mod->status === Mod::STATUS_PENDING;
        $isAuthorViewing = Auth::id() === $mod->user_id;
        $hasPendingUpdate = $mod->versions()->pending()->exists();
        $canBypassPending = Auth::user()?->is_admin;
        $showUpdateButton = $canManageMod && (!$isPendingMod || $canBypassPending) && (!$hasPendingUpdate || $canBypassPending);
        $showPendingNotice = $hasPendingUpdate && ($canBypassPending || $isAuthorViewing);
        $showAuthorPendingNotice = $isPendingMod && $isAuthorViewing;

        $isLiked = Auth::check() && ModLike::where('mod_id', $mod->id)
            ->where('user_id', Auth::id())
            ->exists();
        $isBookmarked = Auth::check() && Auth::user()->hasBookmarked($mod);

        $uploadedAt = optional($mod->published_at);
        $updatedAt = optional($mod->updated_at);

        $metaDetails = [
            'version' => $mod->version,
            'file_size' => $mod->file_size_label,
            'uploaded_at' => $uploadedAt?->format('F j, Y'),
            'updated_at' => $updatedAt?->format('F j, Y'),
            'uploaded_ago' => $uploadedAt ? $uploadedAt->diffForHumans() : null,
        ];

        return view('mods.show', [
            'mod' => $mod,
            'comments' => $comments,
            'relatedMods' => $relatedMods,
            'breadcrumbs' => $breadcrumbs,
            'primaryCategory' => $primaryCategory,
            'downloadUrl' => $currentVersion ? ($currentVersion->download_url ?: $currentVersion->file_url) : $mod->download_url,
            'downloadFormatted' => number_format($mod->downloads),
            'likesFormatted' => number_format($mod->likes),
            'ratingValue' => $ratingValue,
            'ratingFullStars' => $ratingFullStars,
            'ratingHasHalf' => $ratingHasHalf,
            'ratingCount' => $mod->ratings_count,
            'userRating' => $userRating ? (int) $userRating : null,
            'metaDetails' => $metaDetails,
            'galleryItems' => $galleryItems,
            'galleryJson' => $galleryJson,
            'defaultGalleryImage' => $defaultImage,
            'canManagePin' => $canManagePin,
            'isPinnedByOwner' => $isPinnedByOwner,
            'videos' => $videos,
            'versions' => $versions,
            'currentVersion' => $currentVersion,
            'canManageMod' => $canManageMod,
            'showUpdateButton' => $showUpdateButton,
            'showPendingNotice' => $showPendingNotice,
            'showAuthorPendingNotice' => $showAuthorPendingNotice,
            'isLiked' => $isLiked,
            'isBookmarked' => $isBookmarked,
        ]);
    }

    public function rate(Request $request, ModCategory $category, Mod $mod): RedirectResponse
    {
        abort_unless($mod->status === Mod::STATUS_PUBLISHED, Response::HTTP_NOT_FOUND);

        // Verify that the mod belongs to this category
        abort_unless($mod->categories->contains($category), Response::HTTP_NOT_FOUND);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $userId = $request->user()->getKey();
        $existing = $mod->ratings()->where('user_id', $userId)->first();

        if ($existing && (int) $existing->rating === (int) $data['rating']) {
            $existing->delete();

            return back()->with('status', 'Your rating has been removed.');
        }

        $mod->ratings()->updateOrCreate(
            ['user_id' => $userId],
            ['rating' => (int) $data['rating']]
        );

        return back()->with('status', 'Thanks for rating this mod!');
    }

    public function comment(Request $request, ModCategory $category, Mod $mod): RedirectResponse
    {
        // Verify that the mod belongs to this category
        abort_unless($mod->categories->contains($category), Response::HTTP_NOT_FOUND);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:5', 'max:1500'],
            'parent_id' => ['nullable', 'integer', Rule::exists('mod_comments', 'id')->where('mod_id', $mod->id)],
        ]);

        $comment = $mod->comments()->create([
            'user_id' => Auth::id(),
            'parent_id' => $validated['parent_id'] ?? null,
            'body' => $validated['body'],
            'status' => ModComment::STATUS_APPROVED,
        ]);

        // Log activity for comment
        UserActivity::create([
            'user_id' => Auth::id(),
            'action_type' => UserActivity::TYPE_COMMENT,
            'subject_type' => ModComment::class,
            'subject_id' => $comment->id,
            'metadata' => [
                'mod_id' => $mod->id,
                'mod_title' => $mod->title,
                'comment_excerpt' => substr($validated['body'], 0, 100),
            ],
        ]);

        return back()->with('status', 'Comment posted successfully.');
    }

}
