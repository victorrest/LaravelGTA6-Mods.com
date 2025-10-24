<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModStoreRequest;
use App\Http\Requests\ModUpdateRequest;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Models\ModRevision;
use App\Models\UserActivity;
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

            // Log activity for mod upload
            if ($status === Mod::STATUS_PUBLISHED) {
                UserActivity::create([
                    'user_id' => Auth::id(),
                    'action_type' => UserActivity::TYPE_MOD_UPLOAD,
                    'subject_type' => Mod::class,
                    'subject_id' => $mod->id,
                    'metadata' => [
                        'mod_title' => $mod->title,
                        'mod_version' => $mod->version,
                    ],
                ]);
            }
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

    public function edit(ModCategory $category, Mod $mod)
    {
        abort_unless(Auth::user()?->is($mod->author), 403);

        // Verify that the mod belongs to this category
        abort_unless($mod->categories->contains($category), 404);

        return view('mods.edit', [
            'mod' => $mod->load(['categories', 'galleryImages', 'revisions']),
            'categories' => ModCategory::query()->orderBy('name')->get(),
            'category' => $category,
            'pendingRevision' => $mod->revisions()->where('status', ModRevision::STATUS_PENDING)->latest()->first(),
        ]);
    }

    public function update(ModUpdateRequest $request, ModCategory $category, Mod $mod): RedirectResponse
    {
        abort_unless(Auth::user()?->is($mod->author), 403);

        // Verify that the mod belongs to this category
        abort_unless($mod->categories->contains($category), 404);

        $data = $request->validated();

        $filesForRollback = [];

        DB::beginTransaction();

        try {
            $mediaManifest = [
                'hero_image' => null,
                'gallery_images' => [],
                'mod_file' => null,
                'removed_gallery_image_ids' => $request->input('remove_gallery_image_ids', []),
            ];

            if ($request->hasFile('hero_image')) {
                $mediaManifest['hero_image'] = $this->storeRevisionAsset($request->file('hero_image'), $mod, 'hero', $filesForRollback);
            }

            $galleryFiles = $request->file('gallery_images', []);
            foreach ($galleryFiles as $image) {
                $mediaManifest['gallery_images'][] = $this->storeRevisionAsset($image, $mod, 'gallery', $filesForRollback);
            }

            if ($request->hasFile('mod_file')) {
                $file = $request->file('mod_file');
                $path = $this->storeRevisionAsset($file, $mod, 'files', $filesForRollback);
                $mediaManifest['mod_file'] = [
                    'path' => $path,
                    'size' => round($file->getSize() / 1048576, 2),
                    'original_name' => $file->getClientOriginalName(),
                ];
            }

            if (! ($data['download_url'] ?? null) && ! $mediaManifest['mod_file']) {
                throw ValidationException::withMessages([
                    'download_url' => 'Please provide a download URL or upload a mod file.',
                ]);
            }

            $payload = [
                'title' => $data['title'],
                'version' => $data['version'],
                'download_url' => $data['download_url'] ?? null,
                'description' => $data['description'],
                'file_size' => $data['file_size'] ?? ($mediaManifest['mod_file']['size'] ?? null),
                'category_ids' => $data['category_ids'],
                'changelog' => $data['changelog'] ?? null,
            ];

            $mod->revisions()->create([
                'user_id' => Auth::id(),
                'version' => $data['version'],
                'status' => ModRevision::STATUS_PENDING,
                'changelog' => $data['changelog'] ?? null,
                'payload' => $payload,
                'media_manifest' => $mediaManifest,
            ]);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->deleteFiles($filesForRollback);

            throw $exception;
        }

        cache()->forget('home:landing');

        return redirect()->route('mods.show', [$mod->primary_category, $mod])->with('status', 'A mód frissítése beküldésre került és moderálásra vár.');
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

    private function storeRevisionAsset($file, Mod $mod, string $type, array &$filesForRollback): string
    {
        $directory = 'mod-revisions/' . $mod->getKey() . '/' . $type;
        $path = $file->store($directory, 'public');
        $filesForRollback[] = $path;

        return $path;
    }
}
