<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModStoreRequest;
use App\Http\Requests\ModUpdateRequest;
use App\Models\Mod;
use App\Models\ModCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ModManagementController extends Controller
{
    public function create()
    {
        return view('mods.upload', [
            'categories' => ModCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function store(ModStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $mod = Mod::create([
            'user_id' => Auth::id(),
            'title' => $data['title'],
            'slug' => $this->generateUniqueSlug($data['title']),
            'excerpt' => Str::limit(strip_tags($data['description']), 200),
            'description' => $data['description'],
            'version' => $data['version'],
            'download_url' => $data['download_url'],
            'file_size' => $data['file_size'] ?? null,
            'hero_image_path' => $this->storeHeroImage($request),
            'status' => 'published',
            'published_at' => now(),
        ]);

        $mod->categories()->sync($data['category_ids']);

        return redirect()->route('mods.show', $mod)->with('status', 'Mod published successfully.');
    }

    public function edit(Mod $mod)
    {
        abort_unless(Auth::user()?->is($mod->author), 403);

        return view('mods.edit', [
            'mod' => $mod->load('categories'),
            'categories' => ModCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function update(ModUpdateRequest $request, Mod $mod): RedirectResponse
    {
        abort_unless(Auth::user()?->is($mod->author), 403);

        $data = $request->validated();

        $mod->fill([
            'title' => $data['title'],
            'excerpt' => Str::limit(strip_tags($data['description']), 200),
            'description' => $data['description'],
            'version' => $data['version'],
            'download_url' => $data['download_url'],
            'file_size' => $data['file_size'] ?? null,
        ]);

        if ($imagePath = $this->storeHeroImage($request)) {
            $mod->hero_image_path = $imagePath;
        }

        $mod->save();
        $mod->categories()->sync($data['category_ids']);

        return redirect()->route('mods.show', $mod)->with('status', 'Mod updated successfully.');
    }

    public function myMods()
    {
        $mods = Auth::user()->mods()->with('categories')->orderByDesc('published_at')->get();

        return view('mods.my', [
            'mods' => $mods,
        ]);
    }

    private function storeHeroImage(ModStoreRequest|ModUpdateRequest $request): ?string
    {
        if (! $request->hasFile('hero_image')) {
            return null;
        }

        return $request->file('hero_image')->store('mods/hero-images', 'public');
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $counter = 1;

        while (Mod::where('slug', $slug)->exists()) {
            $slug = $base . '-' . ++$counter;
        }

        return $slug;
    }
}
