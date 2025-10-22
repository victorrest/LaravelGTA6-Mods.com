<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $chunksPath = $basePath . '/chunks';

        if (! $disk->exists($chunksPath) && ! $disk->makeDirectory($chunksPath, 0755, true)) {
            abort(500, 'Unable to prepare chunk directory.');
        }

        $chunkName = str_pad((string) $chunkIndex, 6, '0', STR_PAD_LEFT) . '.part';
        $disk->putFileAs($chunksPath, $chunk, $chunkName);

        if ($chunkIndex + 1 < $totalChunks) {
            return response()->json([
                'status' => 'partial',
                'upload_token' => $uploadToken,
                'chunk_index' => $chunkIndex,
            ]);
        }

        if (! $disk->exists($basePath) && ! $disk->makeDirectory($basePath, 0755, true)) {
            abort(500, 'Unable to prepare upload directory.');
        }

        $finalName = $this->generateFinalFilename($data['original_name']);
        $finalPath = $basePath . '/' . $finalName;
        $absoluteFinalPath = storage_path('app/' . $finalPath);

        if (! is_dir(dirname($absoluteFinalPath)) && ! mkdir(dirname($absoluteFinalPath), 0755, true) && ! is_dir(dirname($absoluteFinalPath))) {
            abort(500, 'Unable to create destination directory.');
        }

        $outputStream = fopen($absoluteFinalPath, 'ab');
        if ($outputStream === false) {
            abort(500, 'Unable to open destination file for writing.');
        }

        for ($index = 0; $index < $totalChunks; $index++) {
            $partPath = storage_path('app/' . $chunksPath . '/' . str_pad((string) $index, 6, '0', STR_PAD_LEFT) . '.part');
            if (! file_exists($partPath)) {
                fclose($outputStream);
                $disk->delete($finalPath);
                abort(500, 'Missing chunk during assembly.');
            }

            $chunkStream = fopen($partPath, 'rb');
            if ($chunkStream === false) {
                fclose($outputStream);
                $disk->delete($finalPath);
                abort(500, 'Unable to open chunk for reading.');
            }

            stream_copy_to_stream($chunkStream, $outputStream);
            fclose($chunkStream);
            unlink($partPath);
        }

        fclose($outputStream);
        $disk->deleteDirectory($chunksPath);

        $sizeBytes = filesize($absoluteFinalPath);

        $meta = [
            'user_id' => Auth::id(),
            'original_name' => $data['original_name'],
            'mime_type' => $data['mime_type'],
            'final_name' => $finalName,
            'size_bytes' => $sizeBytes,
            'upload_category' => $data['upload_category'],
            'completed_at' => now()->timestamp,
        ];

        $disk->put($basePath . '/meta.json', json_encode($meta));

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

            if (! $meta || empty($meta['completed_at'])) {
                continue;
            }

            if (now()->subHours(12)->timestamp > (int) $meta['completed_at']) {
                $disk->deleteDirectory($directory);
            }
        }
    }
}
