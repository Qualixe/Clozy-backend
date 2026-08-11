<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    /** Dashboard list — Settings > Subscribers. */
    public function index(): JsonResponse
    {
        $subscribers = Subscriber::query()->orderByDesc('created_at')->get();

        return response()->json($subscribers->map(fn (Subscriber $s) => [
            'id' => (string) $s->id,
            'email' => $s->email,
            'subscribedAt' => $s->created_at->toIso8601String(),
        ])->values());
    }

    /** Footer newsletter signup — public, no auth. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        // Idempotent — resubscribing with the same email is just a no-op
        // success, not an error the visitor needs to see.
        Subscriber::firstOrCreate(['email' => $validated['email']]);

        return response()->json(['message' => 'Subscribed.'], 201);
    }

    public function destroy(string $id): JsonResponse
    {
        Subscriber::where('id', $id)->delete();

        return response()->json(null, 204);
    }
}
