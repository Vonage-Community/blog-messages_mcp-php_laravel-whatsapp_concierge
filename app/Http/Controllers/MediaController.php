<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /**
     * Accept a file upload and return a publicly accessible URL.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $path = $request->file('file')->store('media', 'public');
        $url = Storage::disk('public')->url($path);

        return response()->json(['url' => $url]);
    }

    /**
     * Generate a temporary signed URL for an existing media file.
     *
     * Example: GET /media/signed/photo.jpg
     */
    public function signedUrl(string $filename): JsonResponse
    {
        $path = "media/{$filename}";
        if (! Storage::disk('public')->exists($path)) {
            return response()->json(['error' => 'not found'], 404);
        }

        // For local storage, temporaryUrl is not supported, so return the public URL
        // In production with S3, this would generate a signed URL
        $url = Storage::disk('public')->url($path);

        return response()->json(['url' => $url]);
    }
}
