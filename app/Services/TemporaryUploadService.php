<?php

namespace App\Services;

use App\Models\TemporaryUpload;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class TemporaryUploadService
{
    private const BASE_DIRECTORY = 'tmp/mod-uploads';

    public function __construct(private ?Filesystem $localDisk = null)
    {
        $this->localDisk = $localDisk ?: Storage::disk('local');
    }

    public function moveToPublic(string $token, string $targetDirectory, ?string $expectedCategory = null): array
    {
        if ($record = TemporaryUpload::query()->find($token)) {
            return $this->moveUsingRecord($record, $targetDirectory, $expectedCategory);
        }

        return $this->moveUsingLegacyMeta($token, $targetDirectory, $expectedCategory);
    }

    public function readMeta(string $token): ?array
    {
        $metaPath = $this->buildPath($token, 'meta.json');

        if (! $this->localDisk->exists($metaPath)) {
            return null;
        }

        $meta = json_decode($this->localDisk->get($metaPath), true);

        return is_array($meta) ? $meta : null;
    }

    private function moveUsingRecord(TemporaryUpload $upload, string $targetDirectory, ?string $expectedCategory): array
    {
        if ((int) $upload->user_id !== (int) Auth::id()) {
            throw new RuntimeException('You are not allowed to use this upload.');
        }

        if ($expectedCategory !== null && $upload->category !== $expectedCategory) {
            throw new RuntimeException('Temporary upload type mismatch.');
        }

        $sourceDiskName = $upload->disk ?: 'local';
        $sourceDisk = $sourceDiskName === 'local' ? $this->localDisk : Storage::disk($sourceDiskName);

        if ($upload->isExpired()) {
            $this->purgeRecord($upload, $sourceDiskName);

            throw new RuntimeException('Temporary upload has expired.');
        }

        $localFilePath = $upload->relative_path;

        if (! $sourceDisk->exists($localFilePath)) {
            $this->purgeRecord($upload, $sourceDiskName);

            throw new RuntimeException('Temporary upload file is missing.');
        }

        $publicDisk = Storage::disk('public');
        $stream = $sourceDisk->readStream($localFilePath);

        if (! $stream) {
            throw new RuntimeException('Unable to read uploaded file.');
        }

        $extension = pathinfo($upload->original_name ?? '', PATHINFO_EXTENSION);
        $extension = $extension ? '.' . $extension : '';
        $finalFileName = Str::uuid() . $extension;
        $finalPath = trim($targetDirectory, '/') . '/' . $finalFileName;

        $result = $publicDisk->writeStream($finalPath, $stream);
        fclose($stream);

        if ($result === false) {
            throw new RuntimeException('Failed to persist uploaded file.');
        }

        $this->purgeRecord($upload, $sourceDiskName);

        return [
            'path' => $finalPath,
            'public_url' => $publicDisk->url($finalPath),
            'size' => round(((int) ($upload->size_bytes ?? 0)) / 1048576, 2),
            'original_name' => $upload->original_name ?? $finalFileName,
            'mime_type' => $upload->mime_type,
        ];
    }

    private function moveUsingLegacyMeta(string $token, string $targetDirectory, ?string $expectedCategory): array
    {
        $meta = $this->readMeta($token);

        if (! $meta) {
            throw new RuntimeException('Temporary upload not found.');
        }

        if ((int) ($meta['user_id'] ?? 0) !== (int) Auth::id()) {
            throw new RuntimeException('You are not allowed to use this upload.');
        }

        $completedAt = (int) ($meta['completed_at'] ?? 0);
        if ($completedAt && now()->subHours(12)->timestamp > $completedAt) {
            $this->purgeLegacyDirectory($token);

            throw new RuntimeException('Temporary upload has expired.');
        }

        if ($expectedCategory !== null && ($meta['upload_category'] ?? null) !== $expectedCategory) {
            throw new RuntimeException('Temporary upload type mismatch.');
        }

        $localFilePath = $this->buildPath($token, $meta['final_name'] ?? null);

        if (! $this->localDisk->exists($localFilePath)) {
            $this->purgeLegacyDirectory($token);

            throw new RuntimeException('Temporary upload file is missing.');
        }

        $publicDisk = Storage::disk('public');
        $stream = $this->localDisk->readStream($localFilePath);

        if (! $stream) {
            throw new RuntimeException('Unable to read uploaded file.');
        }

        $extension = pathinfo($meta['original_name'] ?? '', PATHINFO_EXTENSION);
        $extension = $extension ? '.' . $extension : '';
        $finalFileName = Str::uuid() . $extension;
        $finalPath = trim($targetDirectory, '/') . '/' . $finalFileName;

        $result = $publicDisk->writeStream($finalPath, $stream);
        fclose($stream);

        if ($result === false) {
            throw new RuntimeException('Failed to persist uploaded file.');
        }

        $this->purgeLegacyDirectory($token);

        return [
            'path' => $finalPath,
            'public_url' => $publicDisk->url($finalPath),
            'size' => round(((int) ($meta['size_bytes'] ?? 0)) / 1048576, 2),
            'original_name' => $meta['original_name'] ?? $finalFileName,
            'mime_type' => $meta['mime_type'] ?? null,
        ];
    }

    private function purgeRecord(TemporaryUpload $upload, string $diskName = 'local'): void
    {
        $directory = dirname($upload->relative_path);
        $disk = $diskName === 'local' ? $this->localDisk : Storage::disk($diskName);
        $disk->deleteDirectory($directory);
        $upload->delete();
    }

    private function purgeLegacyDirectory(string $token): void
    {
        $this->localDisk->deleteDirectory($this->buildDirectory($token));
    }

    private function buildPath(string $token, ?string $file = null): string
    {
        $base = $this->buildDirectory($token);

        return $file ? $base . '/' . ltrim($file, '/') : $base;
    }

    private function buildDirectory(string $token): string
    {
        return self::BASE_DIRECTORY . '/' . $token;
    }
}
