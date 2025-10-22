<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AssembleChunkedUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var array<string, mixed> */
    private array $payload;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $payload
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
        $this->onQueue('uploads');
    }

    /**
     * Execute the job.
     *
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $identifier = $this->payload['identifier'];
        $chunkDirectory = "chunks/{$identifier}";

        if (! Storage::disk('local')->exists($chunkDirectory)) {
            throw new RuntimeException('Upload chunks are missing.');
        }

        $token = Str::uuid()->toString();
        $targetDirectory = "tmp/uploads/{$token}";
        Storage::disk('local')->makeDirectory($targetDirectory);

        $finalFilename = 'file.bin';
        $finalPath = storage_path('app/'.$targetDirectory.'/'.$finalFilename);

        $output = fopen($finalPath, 'wb');

        for ($index = 0; $index < $this->payload['total_chunks']; $index++) {
            $chunkPath = storage_path('app/'.$chunkDirectory.'/'.sprintf('chunk_%05d', $index));

            if (! file_exists($chunkPath)) {
                fclose($output);
                throw new RuntimeException("Missing chunk {$index} for upload {$identifier}.");
            }

            $input = fopen($chunkPath, 'rb');
            stream_copy_to_stream($input, $output);
            fclose($input);
            @unlink($chunkPath);
        }

        fclose($output);
        Storage::disk('local')->deleteDirectory($chunkDirectory);

        $meta = [
            'token' => $token,
            'original_name' => $this->payload['filename'],
            'mime_type' => $this->payload['mime_type'] ?? 'application/octet-stream',
            'upload_type' => $this->payload['upload_type'],
            'size' => filesize($finalPath),
            'extension' => pathinfo($this->payload['filename'], PATHINFO_EXTENSION),
            'stored_filename' => $finalFilename,
            'completed_at' => now()->toIso8601String(),
        ];

        Storage::disk('local')->put($targetDirectory.'/meta.json', json_encode($meta));

        return [
            'status' => 'completed',
            'token' => $token,
            'name' => $meta['original_name'],
            'size' => round(($meta['size'] ?? 0) / 1048576, 2),
        ];
    }
}
