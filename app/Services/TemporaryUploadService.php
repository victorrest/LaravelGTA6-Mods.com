<?php

namespace App\Services;

use App\Exceptions\TemporaryUploadMissingException;
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

    public function moveToPublic(string $token, string $targetDirectory): array
    {
        $meta = $this->readMeta($token);

        if (! $meta) {
            throw new TemporaryUploadMissingException('Temporary upload not found.');
        }

        if ((int) ($meta['user_id'] ?? 0) !== Auth::id()) {
            throw new RuntimeException('You are not allowed to use this upload.');
        }

        $localFilePath = $this->buildPath($token, $meta['final_name'] ?? null);

        if (! $this->localDisk->exists($localFilePath)) {
            throw new TemporaryUploadMissingException('Temporary upload file is missing.');
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

        $this->localDisk->deleteDirectory($this->buildDirectory($token));

        return [
            'path' => $finalPath,
            'public_url' => $publicDisk->url($finalPath),
            'size' => round(((int) ($meta['size_bytes'] ?? 0)) / 1048576, 2),
            'original_name' => $meta['original_name'] ?? $finalFileName,
            'mime_type' => $meta['mime_type'] ?? null,
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
