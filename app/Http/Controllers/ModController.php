<?php

namespace App\Http\Controllers;

use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModComment;
use App\Models\UserActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $mod->loadMissing(['author', 'categories', 'galleryImages', 'approvedVideos'])->loadCount(['comments', 'approvedVideos']);

        $comments = $mod->comments()->with('author')->latest()->take(20)->get();
        $videos = $mod->approvedVideos()->with('author')->get();

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

        $galleryImages = collect($mod->galleryImages)
            ->map(fn ($image) => [
                'src' => $image->url,
                'alt' => $mod->title,
            ])->prepend([
                'src' => $mod->hero_image_url,
                'alt' => $mod->title,
            ])->unique('src')->values()->all();

        $authUser = Auth::user();
        $canManagePin = $authUser && $authUser->getKey() === $mod->user_id;
        $isPinnedByOwner = $canManagePin && (int) $authUser->pinned_mod_id === (int) $mod->id;

        return view('mods.show', [
            'mod' => $mod,
            'comments' => $comments,
            'videos' => $videos,
            'relatedMods' => $relatedMods,
            'breadcrumbs' => $breadcrumbs,
            'primaryCategory' => $primaryCategory,
            'downloadUrl' => $mod->download_url,
            'downloadFormatted' => number_format($mod->downloads),
            'likesFormatted' => number_format($mod->likes),
            'ratingValue' => $ratingValue,
            'ratingFullStars' => $ratingFullStars,
            'ratingHasHalf' => $ratingHasHalf,
            'ratingCount' => $mod->ratings_count,
            'userRating' => $userRating ? (int) $userRating : null,
            'metaDetails' => $metaDetails,
            'galleryImages' => $galleryImages,
            'canManagePin' => $canManagePin,
            'isPinnedByOwner' => $isPinnedByOwner,
            'videoCount' => $videos->count(),
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
        ]);

        $comment = $mod->comments()->create([
            'user_id' => Auth::id(),
            'body' => $validated['body'],
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
