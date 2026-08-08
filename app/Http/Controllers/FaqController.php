<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FaqController extends Controller
{
    public function index(): JsonResponse
    {
        $faqs = Faq::orderBy('position')->get();

        return response()->json($faqs->map(fn (Faq $f) => $this->detail($f))->values());
    }

    public function show(string $id): JsonResponse
    {
        $faq = Faq::find($id);

        if (! $faq) {
            return response()->json(['message' => 'FAQ not found'], 404);
        }

        return response()->json($this->detail($faq));
    }

    /** Public list used by the storefront's FAQ page — published only, in display order. */
    public function publicIndex(): JsonResponse
    {
        $faqs = Faq::where('status', 'published')->orderBy('position')->get();

        return response()->json($faqs->map(fn (Faq $f) => $this->detail($f))->values());
    }

    /**
     * Persists the dashboard's drag-and-drop FAQ order. `ids` is the full
     * list of FAQ ids in their new display order.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:faqs,id'],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['ids'] as $index => $id) {
                Faq::where('id', $id)->update(['position' => $index]);
            }
        });

        return response()->json(['message' => 'Order updated']);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,published'],
        ]);

        $faq = Faq::create([
            'question' => $validated['question'],
            'answer' => $validated['answer'] ?? '',
            'status' => $validated['status'],
            'position' => (Faq::max('position') ?? -1) + 1,
        ]);

        return response()->json($this->detail($faq), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $faq = Faq::find($id);

        if (! $faq) {
            return response()->json(['message' => 'FAQ not found'], 404);
        }

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,published'],
        ]);

        $faq->update([
            'question' => $validated['question'],
            'answer' => $validated['answer'] ?? '',
            'status' => $validated['status'],
        ]);

        return response()->json($this->detail($faq->fresh()));
    }

    public function destroy(string $id): JsonResponse
    {
        $faq = Faq::find($id);

        if (! $faq) {
            return response()->json(['message' => 'FAQ not found'], 404);
        }

        $faq->delete();

        return response()->json(null, 204);
    }

    private function detail(Faq $faq): array
    {
        return [
            'id' => (string) $faq->id,
            'question' => $faq->question,
            'answer' => $faq->answer ?? '',
            'status' => $faq->status,
        ];
    }
}
