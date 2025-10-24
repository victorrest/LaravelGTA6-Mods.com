<?php

namespace App\Http\Controllers;

use App\Models\Mod;
use App\Models\ModCategory;
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

    public function show(Mod $mod)
    {
        abort_unless($mod->status === 'published', Response::HTTP_NOT_FOUND);

        $mod->loadMissing(['author', 'categories', 'galleryImages'])->loadCount('comments');

        $comments = $mod->comments()->with('author')->latest()->take(20)->get();

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
            'url' => route('mods.show', $mod),
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

        return view('mods.show', [
            'mod' => $mod,
            'comments' => $comments,
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
        ]);
    }

    public function rate(Request $request, Mod $mod): RedirectResponse
    {
        abort_unless($mod->status === Mod::STATUS_PUBLISHED, Response::HTTP_NOT_FOUND);

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

    public function comment(Request $request, Mod $mod): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:5', 'max:1500'],
        ]);

        $mod->comments()->create([
            'user_id' => Auth::id(),
            'body' => $validated['body'],
        ]);

        return back()->with('status', 'Comment posted successfully.');
    }

}
