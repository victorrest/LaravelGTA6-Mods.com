<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModStoreRequest;
use App\Http\Requests\ModUpdateRequest;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Support\EditorJs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

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

        $user = Auth::user();
        $status = $user?->isAdmin() ? Mod::STATUS_PUBLISHED : Mod::STATUS_PENDING;
        $publishedAt = $status === Mod::STATUS_PUBLISHED ? now() : null;

        $downloadUrl = $data['download_url'] ?? null;
        $expectsUploadedArchive = $request->hasFile('mod_file');

        if (! $downloadUrl && ! $expectsUploadedArchive) {
            throw ValidationException::withMessages([
                'download_url' => 'Please provide a download URL or upload a mod file.',
            ]);
        }
        $filesForRollback = [];

        DB::beginTransaction();

        try {
            $modFile = $this->storeModFile($request, $filesForRollback);

            if (! $downloadUrl && $modFile) {
                $downloadUrl = $modFile['public_url'];
            }

            $heroImagePath = $this->storeHeroImage($request, $filesForRollback);

            $plainDescription = EditorJs::toPlainText($data['description']);

            $mod = Mod::create([
                'user_id' => Auth::id(),
                'title' => $data['title'],
                'slug' => $this->generateUniqueSlug($data['title']),
                'excerpt' => Str::limit($plainDescription, 200),
                'description' => $data['description'],
                'version' => $data['version'],
                'download_url' => $downloadUrl,
                'file_path' => $modFile['path'] ?? null,
                'file_size' => $data['file_size'] ?? ($modFile['size'] ?? null),
                'hero_image_path' => $heroImagePath,
                'status' => $status,
                'published_at' => $publishedAt,
            ]);

            $mod->categories()->sync($data['category_ids']);
            $this->storeGalleryImages($request, $mod, $filesForRollback);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->deleteFiles($filesForRollback);

            throw $exception;
        }

        cache()->forget('home:landing');

        $message = $status === Mod::STATUS_PUBLISHED
            ? 'Mod published successfully.'
            : 'Your mod has been submitted for review. You will be notified once it is approved.';

        return redirect()->route('mods.my')->with('status', $message);
    }

    public function edit(Mod $mod)
    {
        abort_unless(Auth::user()?->is($mod->author), 403);

        return view('mods.edit', [
            'mod' => $mod->load(['categories', 'galleryImages']),
            'categories' => ModCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function update(ModUpdateRequest $request, Mod $mod): RedirectResponse
    {
        abort_unless(Auth::user()?->is($mod->author), 403);

        $data = $request->validated();

        $filesForRollback = [];
        $filesToDeleteAfterCommit = [];

        DB::beginTransaction();

        try {
            $modFile = $this->storeModFile($request, $filesForRollback);

            $plainDescription = EditorJs::toPlainText($data['description']);

            $mod->fill([
                'title' => $data['title'],
                'excerpt' => Str::limit($plainDescription, 200),
                'description' => $data['description'],
                'version' => $data['version'],
                'download_url' => $data['download_url'] ?? $mod->download_url,
                'file_size' => $data['file_size'] ?? $mod->file_size,
            ]);

            if ($imagePath = $this->storeHeroImage($request, $filesForRollback)) {
                if ($mod->hero_image_path && ! Str::startsWith($mod->hero_image_path, ['http://', 'https://'])) {
                    $filesToDeleteAfterCommit[] = $mod->hero_image_path;
                }

                $mod->hero_image_path = $imagePath;
            }

            if ($modFile) {
                if ($mod->file_path && ! Str::startsWith($mod->file_path, ['http://', 'https://'])) {
                    $filesToDeleteAfterCommit[] = $mod->file_path;
                }

                $mod->file_path = $modFile['path'];
                $mod->download_url = $data['download_url'] ?? $modFile['public_url'];
                $mod->file_size = $data['file_size'] ?? $modFile['size'];
            }

            if (! $mod->download_url) {
                throw ValidationException::withMessages([
                    'download_url' => 'Please provide a download URL or upload a mod file.',
                ]);
            }

            $mod->save();
            $mod->categories()->sync($data['category_ids']);

            $removedGalleryPaths = $this->removeGalleryImages($request, $mod);
            if ($removedGalleryPaths) {
                $filesToDeleteAfterCommit = array_merge($filesToDeleteAfterCommit, $removedGalleryPaths);
            }

            $this->storeGalleryImages($request, $mod, $filesForRollback);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->deleteFiles($filesForRollback);

            throw $exception;
        }

        $this->deleteFiles($filesToDeleteAfterCommit);

        cache()->forget('home:landing');

        return redirect()->route('mods.show', $mod)->with('status', 'Mod updated successfully.');
    }

    public function myMods()
    {
        $mods = Auth::user()->mods()->with('categories')->latest('created_at')->get();

        return view('mods.my', [
            'mods' => $mods,
        ]);
    }

    private function storeHeroImage(ModStoreRequest|ModUpdateRequest $request, array &$filesForRollback): ?string
    {
        if (! $request->hasFile('hero_image')) {
            return null;
        }

        $path = $request->file('hero_image')->store('mods/hero-images', 'public');
        $filesForRollback[] = $path;

        return $path;
    }

    private function storeModFile(ModStoreRequest|ModUpdateRequest $request, array &$filesForRollback): ?array
    {
        if (! $request->hasFile('mod_file')) {
            return null;
        }

        $file = $request->file('mod_file');
        $path = $file->store('mods/files', 'public');
        $filesForRollback[] = $path;

        return [
            'path' => $path,
            'public_url' => Storage::disk('public')->url($path),
            'size' => round($file->getSize() / 1048576, 2),
        ];
    }

    private function storeGalleryImages(ModStoreRequest|ModUpdateRequest $request, Mod $mod, array &$filesForRollback): void
    {
        $position = (int) $mod->galleryImages()->max('position');

        $galleryImages = $request->file('gallery_images', []);

        foreach ($galleryImages as $image) {
            $path = $image->store('mods/gallery', 'public');
            $filesForRollback[] = $path;

            $mod->galleryImages()->create([
                'path' => $path,
                'position' => ++$position,
            ]);
        }
    }

    private function removeGalleryImages(ModUpdateRequest $request, Mod $mod): array
    {
        $ids = collect($request->input('remove_gallery_image_ids', []))
            ->filter()
            ->map(fn ($id) => (int) $id);

        if ($ids->isEmpty()) {
            return [];
        }

        $paths = [];

        $mod->galleryImages()
            ->whereIn('id', $ids)
            ->get()
            ->each(function ($image) use (&$paths) {
                if (! Str::startsWith($image->path, ['http://', 'https://'])) {
                    $paths[] = $image->path;
                }

                $image->delete();
            });

        return $paths;
    }

    private function deleteFiles(array $paths): void
    {
        $uniquePaths = array_unique(array_filter($paths));

        foreach ($uniquePaths as $path) {
            if (Str::startsWith($path, ['http://', 'https://'])) {
                continue;
            }

            Storage::disk('public')->delete($path);
        }
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
