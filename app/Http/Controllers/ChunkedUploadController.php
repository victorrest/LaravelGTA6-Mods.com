<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ChunkedUploadController extends Controller
{
    private const BASE_DIRECTORY = 'tmp/mod-uploads';

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'upload_token' => ['required', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1'],
            'original_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'upload_category' => ['required', 'in:mod_archive,hero_image,gallery_image'],
            'chunk' => ['required', 'file'],
        ]);

        /** @var UploadedFile $chunk */
        $chunk = $request->file('chunk');
        $uploadToken = $data['upload_token'];
        $chunkIndex = (int) $data['chunk_index'];
        $totalChunks = (int) $data['total_chunks'];

        if ($chunkIndex >= $totalChunks) {
            abort(422, 'Invalid chunk index.');
        }

        $disk = Storage::disk('local');
        $basePath = self::BASE_DIRECTORY . '/' . $uploadToken;
        $metaPath = $basePath . '/meta.json';

        $disk->makeDirectory($basePath);

        $absoluteBasePath = $disk->path($basePath);
        File::ensureDirectoryExists($absoluteBasePath);

        $meta = null;
        if ($disk->exists($metaPath)) {
            $decoded = json_decode($disk->get($metaPath), true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        if (! $meta) {
            $meta = [
                'user_id' => Auth::id(),
                'original_name' => $data['original_name'],
                'mime_type' => $data['mime_type'],
                'final_name' => $this->generateFinalFilename($data['original_name']),
                'size_bytes' => 0,
                'upload_category' => $data['upload_category'],
                'total_chunks' => $totalChunks,
                'next_index' => 0,
                'started_at' => now()->timestamp,
            ];
        }

        if (($meta['total_chunks'] ?? $totalChunks) !== $totalChunks) {
            abort(409, 'Chunk count mismatch.');
        }

        $expectedIndex = (int) ($meta['next_index'] ?? 0);

        if ($chunkIndex !== $expectedIndex) {
            abort(409, 'Unexpected chunk index.');
        }

        $finalName = $meta['final_name'];
        $finalPath = $basePath . '/' . $finalName;
        $absoluteFinalPath = $disk->path($finalPath);

        File::ensureDirectoryExists(dirname($absoluteFinalPath));

        $destination = fopen($absoluteFinalPath, $chunkIndex === 0 ? 'wb' : 'ab');
        if ($destination === false) {
            abort(500, 'Unable to open destination file for writing.');
        }

        $chunkStream = fopen($chunk->getRealPath(), 'rb');
        if ($chunkStream === false) {
            fclose($destination);
            abort(500, 'Unable to open chunk for reading.');
        }

        $chunkSize = $chunk->getSize();
        if ($chunkSize === null) {
            $chunkSize = @filesize($chunk->getRealPath()) ?: 0;
        }

        stream_copy_to_stream($chunkStream, $destination);
        fclose($chunkStream);
        fclose($destination);

        $meta['next_index'] = $chunkIndex + 1;
        $meta['size_bytes'] = (int) ($meta['size_bytes'] ?? 0) + (int) $chunkSize;

        $disk->put($metaPath, json_encode($meta));

        if ($chunkIndex + 1 < $totalChunks) {
            return response()->json([
                'status' => 'partial',
                'upload_token' => $uploadToken,
                'chunk_index' => $chunkIndex,
            ]);
        }

        $sizeBytes = @filesize($absoluteFinalPath) ?: 0;

        $meta['size_bytes'] = $sizeBytes;
        $meta['completed_at'] = now()->timestamp;
        unset($meta['next_index']);

        $disk->put($metaPath, json_encode($meta));

        $this->cleanExpiredDirectories();

        return response()->json([
            'status' => 'completed',
            'upload_token' => $uploadToken,
            'original_name' => $data['original_name'],
            'size_bytes' => $sizeBytes,
            'size_mb' => round($sizeBytes / 1048576, 2),
            'upload_category' => $data['upload_category'],
        ]);
    }

    private function generateFinalFilename(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $extension = $extension ? '.' . $extension : '';

        return Str::uuid() . $extension;
    }

    private function cleanExpiredDirectories(): void
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::BASE_DIRECTORY)) {
            return;
        }
        $directories = $disk->directories(self::BASE_DIRECTORY);

        foreach ($directories as $directory) {
            $metaPath = $directory . '/meta.json';

            if (! $disk->exists($metaPath)) {
                continue;
            }

            $meta = json_decode($disk->get($metaPath), true);

            if (! $meta) {
                continue;
            }

            $referenceTimestamp = $meta['completed_at'] ?? $meta['started_at'] ?? null;

            if (! $referenceTimestamp) {
                continue;
            }

            if (now()->subHours(12)->timestamp > (int) $referenceTimestamp) {
                $disk->deleteDirectory($directory);
            }
        }
    }
}
