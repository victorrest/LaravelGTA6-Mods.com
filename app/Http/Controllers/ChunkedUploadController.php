<?php

namespace App\Http\Controllers;

use App\Jobs\AssembleChunkedUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ChunkedUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string'],
            'filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1'],
            'upload_type' => ['required', 'string', 'in:mod_file,hero_image,gallery_image'],
            'chunk' => ['required', 'file', 'max:51200'],
        ]);

        $identifier = Str::slug($data['identifier']);

        if ($identifier === '') {
            $identifier = Str::uuid()->toString();
        }
        $chunkDir = "chunks/{$identifier}";

        Storage::disk('local')->makeDirectory($chunkDir);

        $chunkName = sprintf('chunk_%05d', $data['chunk_index']);
        Storage::disk('local')->putFileAs($chunkDir, $data['chunk'], $chunkName);

        $receivedChunks = $data['chunk_index'] + 1;
        $totalChunks = (int) $data['total_chunks'];

        if ($receivedChunks < $totalChunks) {
            return response()->json([
                'status' => 'chunk_stored',
                'received' => $receivedChunks,
                'total' => $totalChunks,
            ]);
        }

        $result = AssembleChunkedUpload::dispatchSync([
            'identifier' => $identifier,
            'filename' => $data['filename'],
            'mime_type' => $data['mime_type'],
            'upload_type' => $data['upload_type'],
            'total_chunks' => $totalChunks,
        ]);

        return response()->json($result, Response::HTTP_CREATED);
    }
}
