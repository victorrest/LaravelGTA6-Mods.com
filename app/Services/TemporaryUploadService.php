<?php

namespace App\Services;

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

    public function resolve(string $token): PendingTemporaryUpload
    {
        $meta = $this->readMeta($token);

        if (! $meta) {
            throw new RuntimeException('Temporary upload not found.');
        }

        if ((int) ($meta['user_id'] ?? 0) !== Auth::id()) {
            throw new RuntimeException('You are not allowed to use this upload.');
        }

        $finalName = $meta['final_name'] ?? null;

        if (! $finalName) {
            throw new RuntimeException('Temporary upload is incomplete.');
        }

        $localFilePath = $this->buildPath($token, $finalName);

        if (! $this->localDisk->exists($localFilePath)) {
            throw new RuntimeException('Temporary upload file is missing.');
        }

        return new PendingTemporaryUpload($this, $token, $meta, $localFilePath);
    }

    public function moveToPublic(string $token, string $targetDirectory): array
    {
        return $this->resolve($token)->moveToPublic($targetDirectory);
    }

    public function persistResolvedUpload(PendingTemporaryUpload $upload, string $targetDirectory): array
    {
        $stream = $this->localDisk->readStream($upload->localPath());

        if (! $stream) {
            throw new RuntimeException('Unable to read uploaded file.');
        }

        $publicDisk = Storage::disk('public');

        $extension = pathinfo($upload->originalName() ?? '', PATHINFO_EXTENSION);
        $extension = $extension ? '.' . $extension : '';
        $finalFileName = Str::uuid() . $extension;
        $finalPath = trim($targetDirectory, '/') . '/' . $finalFileName;

        $result = $publicDisk->writeStream($finalPath, $stream);
        fclose($stream);

        if ($result === false) {
            throw new RuntimeException('Failed to persist uploaded file.');
        }

        $this->localDisk->deleteDirectory($this->buildDirectory($upload->token()));

        return [
            'path' => $finalPath,
            'public_url' => $publicDisk->url($finalPath),
            'size' => $upload->sizeMegabytes(),
            'original_name' => $upload->originalName() ?? $finalFileName,
            'mime_type' => $upload->mimeType(),
        ];
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
