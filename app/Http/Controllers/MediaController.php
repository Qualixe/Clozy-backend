<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function index(): JsonResponse
    {
        $media = Media::orderByDesc('created_at')->get();

        return response()->json($media->map(fn (Media $m) => $this->summarize($m))->values());
    }

    /**
     * Streams an uploaded file straight from storage — used instead of the
     * `public/storage` symlink, which `php artisan serve` can fail to
     * traverse on Windows (permission-denied even though the link exists).
     */
    public function serve(string $filename): StreamedResponse
    {
        $path = 'media/'.basename($filename);

        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:51200',
                'mimes:jpg,jpeg,png,webp,gif,avif,mp4,webm,mov,quicktime',
            ],
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
