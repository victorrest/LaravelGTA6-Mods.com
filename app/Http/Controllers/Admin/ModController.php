<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Support\EditorJs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ModController extends Controller
{
    public function index(Request $request)
    {
        $query = Mod::query()->with(['author', 'categories'])->withCount('comments');

        if ($status = $request->string('status')->toString()) {
            if (array_key_exists($status, Mod::STATUS_LABELS)) {
                $query->where('status', $status);
            }
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('author', fn ($authorQuery) => $authorQuery->where('name', 'like', "%{$search}%"));
            });
        }

        $mods = $query->latest('created_at')->paginate(20)->withQueryString();

        return view('admin.mods.index', [
            'mods' => $mods,
        ]);
    }

    public function edit(Mod $mod)
    {
        $mod->load(['categories', 'author']);

        return view('admin.mods.edit', [
            'mod' => $mod,
            'categories' => ModCategory::query()->orderBy('name')->get(),
            'statuses' => Mod::STATUS_LABELS,
        ]);
    }

    public function update(Request $request, Mod $mod): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:160', Rule::unique('mods', 'slug')->ignore($mod->id)],
            'excerpt' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'json'],
            'download_url' => ['required', 'url'],
            'version' => ['required', 'string', 'max:50'],
            'file_size' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(array_keys(Mod::STATUS_LABELS))],
            'featured' => ['sometimes', 'boolean'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'exists:mod_categories,id'],
            'hero_image' => ['nullable', 'image', 'max:4096'],
        ]);

        $descriptionPlain = EditorJs::toPlainText($validated['description']);
        $excerpt = $validated['excerpt'] ?? null;

        if (! $excerpt) {
            $excerpt = Str::limit($descriptionPlain, 200);
        }

        $mod->fill([
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'excerpt' => $excerpt,
            'description' => $validated['description'],
            'download_url' => $validated['download_url'],
            'version' => $validated['version'],
            'file_size' => $validated['file_size'] ?? null,
            'status' => $validated['status'],
            'featured' => $request->boolean('featured'),
        ]);

        if ($request->hasFile('hero_image')) {
            if ($mod->hero_image_path && ! str_starts_with($mod->hero_image_path, ['http://', 'https://'])) {
                Storage::disk('public')->delete($mod->hero_image_path);
            }

            $mod->hero_image_path = $request->file('hero_image')->store('mods/hero-images', 'public');
        }

        if ($mod->status === Mod::STATUS_PUBLISHED && ! $mod->published_at) {
            $mod->published_at = now();
        }

        if ($mod->status !== Mod::STATUS_PUBLISHED) {
            $mod->published_at = null;
        }

        $mod->save();
        $mod->categories()->sync($validated['category_ids']);

        cache()->forget('home:landing');

        return redirect()->route('admin.mods.edit', $mod)->with('status', 'Mod updated successfully.');
    }

    public function destroy(Mod $mod): RedirectResponse
    {
        $mod->categories()->detach();
        $mod->delete();

        cache()->forget('home:landing');

        return redirect()->route('admin.mods.index')->with('status', 'Mod removed successfully.');
    }
}
