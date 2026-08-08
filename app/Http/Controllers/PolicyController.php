<?php

namespace App\Http\Controllers;

use App\Models\Policy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PolicyController extends Controller
{
    public function index(): JsonResponse
    {
        $policies = Policy::orderBy('title')->get();

        return response()->json($policies->map(fn (Policy $p) => $this->summary($p))->values());
    }

    public function show(string $id): JsonResponse
    {
        $policy = Policy::find($id);

        if (! $policy) {
            return response()->json(['message' => 'Policy not found'], 404);
        }

        return response()->json($this->detail($policy));
    }

    /** Public list used by the storefront's policies index page — published only. */
    public function publicIndex(): JsonResponse
    {
        $policies = Policy::where('status', 'published')->orderBy('title')->get();

        return response()->json($policies->map(fn (Policy $p) => $this->summary($p))->values());
    }

    /** Public lookup used by the storefront to render a policy by its slug. */
    public function showBySlug(string $slug): JsonResponse
    {
        $policy = Policy::where('slug', $slug)->where('status', 'published')->first();

        if (! $policy) {
            return response()->json(['message' => 'Policy not found'], 404);
        }

        return response()->json($this->detail($policy));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,published'],
        ]);

        $policy = Policy::create([
            'title' => $validated['title'],
            'slug' => $this->uniqueSlug($validated['slug'] ?: $validated['title']),
            'content' => $validated['content'] ?? '',
            'status' => $validated['status'],
        ]);

        return response()->json($this->detail($policy), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $policy = Policy::find($id);

        if (! $policy) {
            return response()->json(['message' => 'Policy not found'], 404);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,published'],
        ]);

        $slug = $validated['slug'] ?: $validated['title'];

        $policy->update([
            'title' => $validated['title'],
            'slug' => $slug !== $policy->slug ? $this->uniqueSlug($slug, $policy->id) : $policy->slug,
            'content' => $validated['content'] ?? '',
            'status' => $validated['status'],
        ]);

        return response()->json($this->detail($policy->fresh()));
    }

    public function destroy(string $id): JsonResponse
    {
        $policy = Policy::find($id);

        if (! $policy) {
            return response()->json(['message' => 'Policy not found'], 404);
        }

        $policy->delete();

        return response()->json(null, 204);
    }

    private function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'policy';
        $slug = $base;
        $suffix = 1;

        while (
            Policy::where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    private function summary(Policy $policy): array
    {
        return [
            'id' => (string) $policy->id,
            'title' => $policy->title,
            'slug' => $policy->slug,
            'status' => $policy->status,
            'updatedAt' => $policy->updated_at?->toIso8601String(),
        ];
    }

    private function detail(Policy $policy): array
    {
        return [
            'id' => (string) $policy->id,
            'title' => $policy->title,
            'slug' => $policy->slug,
            'content' => $policy->content ?? '',
            'status' => $policy->status,
            'updatedAt' => $policy->updated_at?->toIso8601String(),
        ];
    }
}
