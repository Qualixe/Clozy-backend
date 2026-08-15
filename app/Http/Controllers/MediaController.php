<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

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
     *
     * Every filename is a random, unique token assigned once at upload time
     * (see store() below) and is never reused or overwritten — destroy()
     * deletes it outright rather than replacing its contents — so it's safe
     * to tell browsers/proxies to cache it indefinitely. An ETag/
     * Last-Modified pair is still sent (and honored via If-None-Match) for
     * any client or intermediary proxy that revalidates anyway.
     */
    public function serve(Request $request, string $filename): Response
    {
        $path = 'media/'.basename($filename);
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            abort(404);
        }

        $lastModifiedAt = $disk->lastModified($path);
        $etag = '"'.md5($path.$lastModifiedAt).'"';

        $headers = [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModifiedAt).' GMT',
        ];

        if ($request->headers->get('If-None-Match') === $etag) {
            return response()->noContent(304)->withHeaders($headers);
        }

        return $disk->response($path, null, $headers);
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
