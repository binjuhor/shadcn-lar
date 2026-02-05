<?php

namespace App\Http\Controllers;

use App\Services\PresignedUrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function __construct(
        private PresignedUrlService $presignedUrlService,
    ) {}

    public function generatePresignedUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bucketId' => ['required', 'string'],
            'filename' => ['required', 'string', 'max:255'],
            'mimeType' => ['required', 'string'],
            'contentLength' => ['required', 'integer', 'min:1', 'max:10485760'],
            'unique' => ['sometimes', 'boolean'],
        ]);

        $result = $this->presignedUrlService->generateUploadUrl(
            bucketId: $validated['bucketId'],
            filename: $validated['filename'],
            mimeType: $validated['mimeType'],
            contentLength: $validated['contentLength'],
            unique: $validated['unique'] ?? true,
        );

        return response()->json($result);
    }
}
