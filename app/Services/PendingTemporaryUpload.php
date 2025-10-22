<?php

namespace App\Services;

class PendingTemporaryUpload
{
    public function __construct(
        private TemporaryUploadService $service,
        private string $token,
        private array $meta,
        private string $localPath,
    ) {
    }

    public function token(): string
    {
        return $this->token;
    }

    public function originalName(): ?string
    {
        return $this->meta['original_name'] ?? null;
    }

    public function mimeType(): ?string
    {
        return $this->meta['mime_type'] ?? null;
    }

    public function sizeBytes(): int
    {
        return (int) ($this->meta['size_bytes'] ?? 0);
    }

    public function sizeMegabytes(): float
    {
        return round($this->sizeBytes() / 1048576, 2);
    }

    public function category(): ?string
    {
        return $this->meta['upload_category'] ?? null;
    }

    public function moveToPublic(string $targetDirectory): array
    {
        return $this->service->persistResolvedUpload($this, $targetDirectory);
    }

    public function localPath(): string
    {
        return $this->localPath;
    }

    public function meta(): array
    {
        return $this->meta;
    }
}
