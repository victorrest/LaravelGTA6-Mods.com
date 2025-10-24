<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mod;
use App\Models\ModGalleryImage;
use App\Models\ModRevision;
use App\Support\EditorJs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ModRevisionController extends Controller
{
    public function index(): View
    {
        $revisions = ModRevision::query()
            ->with(['mod.author'])
            ->latest()
            ->paginate(20);

        return view('admin.mods.revisions.index', [
            'revisions' => $revisions,
        ]);
    }

    public function show(ModRevision $modRevision): View
    {
        $modRevision->load(['mod.author', 'author']);

        return view('admin.mods.revisions.show', [
            'revision' => $modRevision,
            'mod' => $modRevision->mod->load(['categories', 'galleryImages']),
        ]);
    }

    public function approve(ModRevision $modRevision): RedirectResponse
    {
        if ($modRevision->status !== ModRevision::STATUS_PENDING) {
            return back()->with('status', 'Ez a frissítés már feldolgozásra került.');
        }

        $mod = $modRevision->mod;

        DB::beginTransaction();

        try {
            $payload = $modRevision->payload;
            $manifest = $modRevision->media_manifest ?? [];
            $filesToDelete = [];

            $mod->fill([
                'title' => $payload['title'],
                'description' => $payload['description'],
                'version' => $payload['version'],
                'download_url' => $payload['download_url'] ?? $mod->download_url,
                'file_size' => $payload['file_size'] ?? $mod->file_size,
                'excerpt' => Str::limit(EditorJs::toPlainText($payload['description']), 200),
            ]);

            if (! empty($manifest['hero_image'])) {
                $newPath = $this->finalizeRevisionAsset($manifest['hero_image'], 'mods/hero-images');

                if ($mod->hero_image_path && ! Str::startsWith($mod->hero_image_path, ['http://', 'https://'])) {
                    $filesToDelete[] = $mod->hero_image_path;
                }

                $mod->hero_image_path = $newPath;
            }

            if (! empty($manifest['mod_file']['path'])) {
                $finalPath = $this->finalizeRevisionAsset($manifest['mod_file']['path'], 'mods/files');

                if ($mod->file_path && ! Str::startsWith($mod->file_path, ['http://', 'https://'])) {
                    $filesToDelete[] = $mod->file_path;
                }

                $mod->file_path = $finalPath;
                $mod->download_url = $payload['download_url'] ?? Storage::disk('public')->url($finalPath);
                $mod->file_size = $manifest['mod_file']['size'] ?? $mod->file_size;
            }

            $mod->save();
            $mod->categories()->sync($payload['category_ids'] ?? []);

            $this->applyGalleryChanges($mod, $manifest, $filesToDelete);

            $modRevision->update([
                'status' => ModRevision::STATUS_APPROVED,
                'approved_at' => now(),
            ]);

            DB::commit();

            foreach ($filesToDelete as $path) {
                Storage::disk('public')->delete($path);
            }

            $this->cleanupRevisionMedia($manifest);
        } catch (Throwable $exception) {
            DB::rollBack();
            Log::error('Failed to approve mod revision', [
                'revision_id' => $modRevision->getKey(),
                'exception' => $exception,
            ]);

            return back()->withErrors('Nem sikerült alkalmazni a frissítést.');
        }

        return redirect()->route('admin.mod-revisions.index')->with('status', 'A frissítés publikálásra került.');
    }

    public function reject(ModRevision $modRevision): RedirectResponse
    {
        if ($modRevision->status !== ModRevision::STATUS_PENDING) {
            return back()->with('status', 'Ez a frissítés már feldolgozásra került.');
        }

        $modRevision->update([
            'status' => ModRevision::STATUS_REJECTED,
            'rejected_at' => now(),
        ]);

        $this->cleanupRevisionMedia($modRevision->media_manifest ?? []);

        return back()->with('status', 'A frissítés elutasításra került.');
    }

    protected function finalizeRevisionAsset(string $path, string $targetDirectory): string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return $path;
        }

        $filename = basename($path);
        $target = trim($targetDirectory, '/') . '/' . uniqid() . '-' . $filename;
        $disk->move($path, $target);

        return $target;
    }

    protected function applyGalleryChanges(Mod $mod, array $manifest, array &$filesToDelete): void
    {
        $removedIds = collect($manifest['removed_gallery_image_ids'] ?? [])->map(fn ($id) => (int) $id);

        if ($removedIds->isNotEmpty()) {
            $mod->galleryImages()
                ->whereIn('id', $removedIds)
                ->get()
                ->each(function (ModGalleryImage $image) use (&$filesToDelete) {
                    if (! Str::startsWith($image->path, ['http://', 'https://'])) {
                        $filesToDelete[] = $image->path;
                    }

                    $image->delete();
                });
        }

        $newImages = $manifest['gallery_images'] ?? [];
        if (! empty($newImages)) {
            $position = (int) $mod->galleryImages()->max('position');
            foreach ($newImages as $imagePath) {
                $finalPath = $this->finalizeRevisionAsset($imagePath, 'mods/gallery');
                $mod->galleryImages()->create([
                    'path' => $finalPath,
                    'position' => ++$position,
                ]);
            }
        }
    }

    protected function cleanupRevisionMedia(array $manifest): void
    {
        $disk = Storage::disk('public');
        $paths = [];

        if (! empty($manifest['hero_image'])) {
            $paths[] = $manifest['hero_image'];
        }

        if (! empty($manifest['mod_file']['path'])) {
            $paths[] = $manifest['mod_file']['path'];
        }

        foreach ($manifest['gallery_images'] ?? [] as $path) {
            $paths[] = $path;
        }

        foreach ($paths as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }
}
