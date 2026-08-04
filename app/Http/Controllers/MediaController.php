<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function index(): JsonResponse
    {
        $media = Media::orderByDesc('created_at')->get();

        return response()->json($media->map(fn (Media $m) => $this->summarize($m))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'image', 'max:8192', 'mimes:jpg,jpeg,png,webp,gif,avif'],
        ]);

        $file = $validated['file'];
        $path = $file->store('media', 'public');

        $media = Media::create([
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json($this->summarize($media), 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $media = Media::find($id);

        if (! $media) {
            return response()->json(['message' => 'Media not found'], 404);
        }

        Storage::disk('public')->delete($media->path);
        $media->delete();

        return response()->json(null, 204);
    }

    private function summarize(Media $media): array
    {
        return [
            'id' => (string) $media->id,
            'url' => $media->url,
            'filename' => $media->filename,
            'mimeType' => $media->mime_type,
            'size' => $media->size,
            'createdAt' => $media->created_at?->toIso8601String(),
        ];
    }
}
