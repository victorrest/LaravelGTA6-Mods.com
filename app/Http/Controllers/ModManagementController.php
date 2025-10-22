<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModStoreRequest;
use App\Http\Requests\ModUpdateRequest;
use App\Models\Mod;
use App\Models\ModCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonException;
use Illuminate\Validation\ValidationException;

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

        $heroImagePath = $this->storeHeroImage($request);
        $modFile = $this->storeModFile($request);

        $downloadUrl = $data['download_url'] ?? null;

        if (! $downloadUrl && $modFile) {
            $downloadUrl = $modFile['public_url'];
        }

        if (! $downloadUrl) {
            throw ValidationException::withMessages([
                'download_url' => 'Please provide a download URL or upload a mod file.',
            ]);
        }

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
        $this->storeGalleryImages($request, $mod);

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

        $modFile = $this->storeModFile($request);

        $mod->fill([
            'title' => $data['title'],
            'excerpt' => Str::limit(strip_tags($data['description']), 200),
            'description' => $data['description'],
            'version' => $data['version'],
            'download_url' => $data['download_url'] ?? $mod->download_url,
            'file_size' => $data['file_size'] ?? $mod->file_size,
        ]);

        if ($imagePath = $this->storeHeroImage($request)) {
            $mod->hero_image_path = $imagePath;
        }

        if ($modFile) {
            if ($mod->file_path && ! Str::startsWith($mod->file_path, ['http://', 'https://'])) {
                Storage::disk('public')->delete($mod->file_path);
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
        $this->removeGalleryImages($request, $mod);
        $this->storeGalleryImages($request, $mod);

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

    private function storeHeroImage(ModStoreRequest|ModUpdateRequest $request): ?string
    {
        if (! $request->hasFile('hero_image')) {
            return null;
        }

        return $request->file('hero_image')->store('mods/hero-images', 'public');
    }

    private function storeModFile(ModStoreRequest|ModUpdateRequest $request): ?array
    {
        if ($token = $request->input('mod_file_token')) {
            return $this->consumeChunkedUpload($token, 'mod_file');
        }

        if (! $request->hasFile('mod_file')) {
            return null;
        }

        $file = $request->file('mod_file');
        $path = $file->store('mods/files', 'public');

        return [
            'path' => $path,
            'public_url' => Storage::disk('public')->url($path),
            'size' => round($file->getSize() / 1048576, 2),
        ];
    }

    private function consumeChunkedUpload(string $token, string $expectedType): array
    {
        $baseDirectory = "tmp/uploads/{$token}";
        $disk = Storage::disk('local');

        if (! $disk->exists($baseDirectory.'/meta.json')) {
            throw ValidationException::withMessages([
                'mod_file' => 'The provided upload token is invalid or has expired. Please re-upload the file.',
            ]);
        }

        try {
            $meta = json_decode($disk->get($baseDirectory.'/meta.json'), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'mod_file' => 'Uploaded file metadata is corrupted. Please retry the upload.',
            ]);
        }

        if (($meta['upload_type'] ?? null) !== $expectedType) {
            throw ValidationException::withMessages([
                'mod_file' => 'The provided upload token does not match the expected file type.',
            ]);
        }

        $source = $baseDirectory.'/'.($meta['stored_filename'] ?? 'file.bin');

        if (! $disk->exists($source)) {
            throw ValidationException::withMessages([
                'mod_file' => 'Uploaded chunks could not be located. Please try again.',
            ]);
        }

        $extension = $meta['extension'] ?? pathinfo($meta['original_name'] ?? '', PATHINFO_EXTENSION);
        $finalName = Str::random(40).($extension ? '.'.$extension : '');
        $finalPath = 'mods/files/'.$finalName;

        $stream = $disk->readStream($source);
        $stored = Storage::disk('public')->put($finalPath, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        if (! $stored) {
            throw ValidationException::withMessages([
                'mod_file' => 'We could not finalise the uploaded file. Please try again.',
            ]);
        }

        $disk->deleteDirectory($baseDirectory);

        $sizeBytes = $meta['size'] ?? Storage::disk('public')->size($finalPath);

        return [
            'path' => $finalPath,
            'public_url' => Storage::disk('public')->url($finalPath),
            'size' => round(($sizeBytes ?? 0) / 1048576, 2),
        ];
    }

    private function storeGalleryImages(ModStoreRequest|ModUpdateRequest $request, Mod $mod): void
    {
        $galleryImages = $request->file('gallery_images', []);

        if (! $galleryImages) {
            return;
        }

        $position = (int) $mod->galleryImages()->max('position');

        foreach ($galleryImages as $image) {
            $mod->galleryImages()->create([
                'path' => $image->store('mods/gallery', 'public'),
                'position' => ++$position,
            ]);
        }
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
}
