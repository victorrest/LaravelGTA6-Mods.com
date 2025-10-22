<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModStoreRequest;
use App\Http\Requests\ModUpdateRequest;
use App\Models\Mod;
use App\Models\ModCategory;
use App\Services\PendingTemporaryUpload;
use App\Services\TemporaryUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ModManagementController extends Controller
{
    public function __construct(private TemporaryUploadService $temporaryUploadService)
    {
    }

    public function create()
    {
        return view('mods.upload', [
            'categories' => ModCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function store(ModStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $pendingModUpload = $this->resolvePendingUploadToken(
            $request,
            'mod_file_token',
            'mod_archive',
            'mod_file',
            'Your mod archive upload has expired. Please upload the file again.'
        );

        $pendingHeroUpload = $this->resolvePendingUploadToken(
            $request,
            'hero_image_token',
            'hero_image',
            'hero_image',
            'Your hero image upload has expired. Please upload it again.'
        );

        $pendingGalleryUploads = $this->resolveGalleryUploads($request);

        $downloadUrl = $data['download_url'] ?? null;

        if (! $downloadUrl && ! $pendingModUpload && ! $request->hasFile('mod_file')) {
            throw ValidationException::withMessages([
                'download_url' => 'Please provide a download URL or upload a mod file.',
            ]);
        }

        $user = Auth::user();
        $status = $user?->isAdmin() ? Mod::STATUS_PUBLISHED : Mod::STATUS_PENDING;
        $publishedAt = $status === Mod::STATUS_PUBLISHED ? now() : null;

        $storedFiles = [];
        $galleryPaths = [];
        $modFile = null;
        $heroImagePath = null;

        try {
            $modFile = $this->storeModArchiveFromRequest($request, $pendingModUpload, $storedFiles);

            if (! $downloadUrl && $modFile) {
                $downloadUrl = $modFile['public_url'];
            }

            $heroImagePath = $this->storeHeroImageFromRequest($request, $pendingHeroUpload, $storedFiles);

            $galleryPaths = $this->storeGalleryImagesFromRequest($request, $pendingGalleryUploads, $storedFiles);

            DB::beginTransaction();

            $mod = Mod::create([
                'user_id' => Auth::id(),
                'title' => $data['title'],
                'slug' => $this->generateUniqueSlug($data['title']),
                'excerpt' => Str::limit(strip_tags($data['description']), 200),
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

            $this->appendGalleryImages($mod, $galleryPaths);

            DB::commit();
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->cleanupStoredFiles($storedFiles);

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

        $pendingModUpload = $this->resolvePendingUploadToken(
            $request,
            'mod_file_token',
            'mod_archive',
            'mod_file',
            'Your mod archive upload has expired. Please upload the file again.'
        );

        $pendingHeroUpload = $this->resolvePendingUploadToken(
            $request,
            'hero_image_token',
            'hero_image',
            'hero_image',
            'Your hero image upload has expired. Please upload it again.'
        );

        $pendingGalleryUploads = $this->resolveGalleryUploads($request);

        $downloadUrlInput = $data['download_url'] ?? null;
        $storedFiles = [];
        $newGalleryPaths = [];
        $newModFile = null;
        $newHeroPath = null;
        $replaceModFile = false;
        $replaceHeroImage = false;
        $downloadUrl = $downloadUrlInput ?? $mod->download_url;
        $fileSize = $data['file_size'] ?? $mod->file_size;
        $originalModFilePath = $mod->file_path;
        $originalHeroImagePath = $mod->hero_image_path;

        try {
            if ($pendingModUpload || $request->hasFile('mod_file')) {
                $newModFile = $this->storeModArchiveFromRequest($request, $pendingModUpload, $storedFiles);

                if ($newModFile) {
                    $replaceModFile = true;
                    $downloadUrl = $downloadUrlInput ?? $newModFile['public_url'];
                    $fileSize = $data['file_size'] ?? $newModFile['size'];
                }
            }

            if (! $downloadUrl) {
                throw ValidationException::withMessages([
                    'download_url' => 'Please provide a download URL or upload a mod file.',
                ]);
            }

            $newHeroPath = $this->storeHeroImageFromRequest($request, $pendingHeroUpload, $storedFiles);

            if ($newHeroPath) {
                $replaceHeroImage = true;
            }

            $newGalleryPaths = $this->storeGalleryImagesFromRequest($request, $pendingGalleryUploads, $storedFiles);

            DB::beginTransaction();

            $mod->fill([
                'title' => $data['title'],
                'excerpt' => Str::limit(strip_tags($data['description']), 200),
                'description' => $data['description'],
                'version' => $data['version'],
                'download_url' => $downloadUrl,
                'file_size' => $fileSize,
            ]);

            if ($replaceHeroImage) {
                $mod->hero_image_path = $newHeroPath;
            }

            if ($replaceModFile && $newModFile) {
                $mod->file_path = $newModFile['path'];
                $mod->download_url = $downloadUrl;
                $mod->file_size = $fileSize;
            }

            $mod->save();

            $mod->categories()->sync($data['category_ids']);

            $this->removeGalleryImages($request, $mod);
            $this->appendGalleryImages($mod, $newGalleryPaths);

            DB::commit();
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->cleanupStoredFiles($storedFiles);

            throw $exception;
        }

        if ($replaceModFile && $originalModFilePath && ! Str::startsWith($originalModFilePath, ['http://', 'https://'])) {
            Storage::disk('public')->delete($originalModFilePath);
        }

        if ($replaceHeroImage && $originalHeroImagePath && ! Str::startsWith($originalHeroImagePath, ['http://', 'https://'])) {
            Storage::disk('public')->delete($originalHeroImagePath);
        }

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

    private function removeGalleryImages(ModUpdateRequest $request, Mod $mod): void
    {
        $ids = collect($request->input('remove_gallery_image_ids', []))
            ->filter()
            ->map(fn ($id) => (int) $id);

        if ($ids->isEmpty()) {
            return;
        }

        $mod->galleryImages()
            ->whereIn('id', $ids)
            ->get()
            ->each(function ($image) {
                if (! Str::startsWith($image->path, ['http://', 'https://'])) {
                    Storage::disk('public')->delete($image->path);
                }
                $image->delete();
            });
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

    private function resolvePendingUploadToken(
        ModStoreRequest|ModUpdateRequest $request,
        string $field,
        string $expectedCategory,
        string $errorField,
        string $errorMessage,
    ): ?PendingTemporaryUpload {
        $token = $request->input($field);

        if (! $token) {
            return null;
        }

        try {
            $upload = $this->temporaryUploadService->resolve($token);
        } catch (RuntimeException $exception) {
            $this->replaceRequestValue($request, $field, null);

            throw ValidationException::withMessages([
                $errorField => $errorMessage,
            ]);
        }

        if ($upload->category() !== $expectedCategory) {
            $this->replaceRequestValue($request, $field, null);

            throw ValidationException::withMessages([
                $errorField => 'The provided upload token is invalid for this field. Please upload the file again.',
            ]);
        }

        return $upload;
    }

    /**
     * @return array<int, PendingTemporaryUpload>
     */
    private function resolveGalleryUploads(ModStoreRequest|ModUpdateRequest $request): array
    {
        $tokens = collect($request->input('gallery_image_tokens', []))
            ->filter()
            ->values();

        if ($tokens->isEmpty()) {
            return [];
        }

        $uploads = [];

        foreach ($tokens as $token) {
            try {
                $upload = $this->temporaryUploadService->resolve($token);
            } catch (RuntimeException $exception) {
                $this->replaceRequestValue($request, 'gallery_image_tokens', []);

                throw ValidationException::withMessages([
                    'gallery_images' => 'One or more screenshot uploads have expired. Please upload them again.',
                ]);
            }

            if ($upload->category() !== 'gallery_image') {
                $this->replaceRequestValue($request, 'gallery_image_tokens', []);

                throw ValidationException::withMessages([
                    'gallery_images' => 'One or more screenshot uploads are invalid. Please upload them again.',
                ]);
            }

            $uploads[] = $upload;
        }

        return $uploads;
    }

    private function storeModArchiveFromRequest(
        ModStoreRequest|ModUpdateRequest $request,
        ?PendingTemporaryUpload $pendingUpload,
        array &$storedFiles,
    ): ?array {
        if ($pendingUpload) {
            $upload = $this->finalizePendingUpload(
                $pendingUpload,
                'mods/files',
                'mod_file',
                'Your mod archive upload has expired. Please upload the file again.',
                $storedFiles,
            );

            return $upload;
        }

        if (! $request->hasFile('mod_file')) {
            return null;
        }

        try {
            $file = $request->file('mod_file');
            $path = $file->store('mods/files', 'public');
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'mod_file' => 'Failed to store the mod archive. Please try again.',
            ]);
        }

        $storedFiles[] = ['disk' => 'public', 'path' => $path];

        return [
            'path' => $path,
            'public_url' => Storage::disk('public')->url($path),
            'size' => round($file->getSize() / 1048576, 2),
        ];
    }

    private function storeHeroImageFromRequest(
        ModStoreRequest|ModUpdateRequest $request,
        ?PendingTemporaryUpload $pendingUpload,
        array &$storedFiles,
    ): ?string {
        if ($pendingUpload) {
            $upload = $this->finalizePendingUpload(
                $pendingUpload,
                'mods/hero-images',
                'hero_image',
                'Your hero image upload has expired. Please upload it again.',
                $storedFiles,
            );

            return $upload['path'] ?? null;
        }

        if (! $request->hasFile('hero_image')) {
            return null;
        }

        try {
            $path = $request->file('hero_image')->store('mods/hero-images', 'public');
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'hero_image' => 'Failed to store the hero image. Please try again.',
            ]);
        }

        $storedFiles[] = ['disk' => 'public', 'path' => $path];

        return $path;
    }

    /**
     * @param array<int, PendingTemporaryUpload> $pendingUploads
     * @return array<int, string>
     */
    private function storeGalleryImagesFromRequest(
        ModStoreRequest|ModUpdateRequest $request,
        array $pendingUploads,
        array &$storedFiles,
    ): array {
        $paths = [];

        foreach ($pendingUploads as $pendingUpload) {
            $upload = $this->finalizePendingUpload(
                $pendingUpload,
                'mods/gallery',
                'gallery_images',
                'One or more screenshot uploads have expired. Please upload them again.',
                $storedFiles,
            );

            if (! empty($upload['path'])) {
                $paths[] = $upload['path'];
            }
        }

        $galleryImages = $request->file('gallery_images', []);

        foreach ($galleryImages as $image) {
            try {
                $path = $image->store('mods/gallery', 'public');
            } catch (Throwable $exception) {
                throw ValidationException::withMessages([
                    'gallery_images' => 'Failed to store one of the gallery images. Please try again.',
                ]);
            }

            $storedFiles[] = ['disk' => 'public', 'path' => $path];
            $paths[] = $path;
        }

        return $paths;
    }

    private function appendGalleryImages(Mod $mod, array $paths): void
    {
        if (empty($paths)) {
            return;
        }

        $position = (int) $mod->galleryImages()->max('position');

        foreach ($paths as $path) {
            $mod->galleryImages()->create([
                'path' => $path,
                'position' => ++$position,
            ]);
        }
    }

    private function finalizePendingUpload(
        PendingTemporaryUpload $upload,
        string $targetDirectory,
        string $errorField,
        string $errorMessage,
        array &$storedFiles,
    ): array {
        try {
            $result = $upload->moveToPublic($targetDirectory);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                $errorField => $errorMessage,
            ]);
        }

        if (! empty($result['path'])) {
            $storedFiles[] = ['disk' => 'public', 'path' => $result['path']];
        }

        return $result;
    }

    private function cleanupStoredFiles(array $storedFiles): void
    {
        foreach ($storedFiles as $file) {
            $disk = $file['disk'] ?? null;
            $path = $file['path'] ?? null;

            if (! $disk || ! $path) {
                continue;
            }

            Storage::disk($disk)->delete($path);
        }
    }

    private function replaceRequestValue(
        ModStoreRequest|ModUpdateRequest $request,
        string $field,
        mixed $value,
    ): void {
        $request->merge([$field => $value]);
    }
}
