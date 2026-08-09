<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::orderBy('name')->get();

        return response()->json($users->map(fn (User $u) => $this->summarize($u))->values());
    }

    public function show(string $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($this->summarize($user));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            // "owner" isn't assignable here — ownership transfer is a
            // separate, guarded flow, not a normal staff-role option.
            'role' => ['required', 'in:admin,staff,user'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(RolePermissions::ALL)],
        ]);

        $user = User::create($validated);
        $this->syncSpatieRole($user);
        $user->syncPermissions($validated['permissions'] ?? RolePermissions::defaultsFor($user->role));

        return response()->json($this->summarize($user), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            // "owner" is only accepted here as a no-op passthrough (editing
            // the owner's own name/email without touching their role) —
            // promoting someone else to owner is a separate, guarded flow.
            'role' => ['required', 'in:owner,admin,staff,user'],
            'password' => ['nullable', 'string', 'min:8'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(RolePermissions::ALL)],
        ]);

        if ($validated['role'] === 'owner' && $user->role !== 'owner') {
            return response()->json(['message' => "Ownership can't be assigned here."], 422);
        }

        $isSelf = $user->id === $request->user()->id;

        if ($isSelf && $validated['role'] !== $user->role) {
            return response()->json(['message' => "You can't change your own role."], 422);
        }

        if ($isSelf && $request->has('permissions')) {
            return response()->json(['message' => "You can't change your own permissions."], 422);
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];
        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        $user->save();
        $this->syncSpatieRole($user);

        if ($request->has('permissions')) {
            $user->syncPermissions($validated['permissions'] ?? []);
        }

        return response()->json($this->summarize($user));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => "You can't delete your own account."], 422);
        }

        $user->delete();

        return response()->json(null, 204);
    }

    /**
     * Keeps the Spatie role assignment in step with the `role` column this
     * controller writes — `owner`/`admin`/`staff` map to their matching
     * Spatie role, and plain customers ("user") hold no dashboard role at
     * all. Demoting someone to "user" also strips their direct permissions
     * (the actual permission source of truth — see RolePermissions), so a
     * former staff member doesn't quietly retain dashboard access.
     */
    private function syncSpatieRole(User $user): void
    {
        $user->syncRoles($user->role === 'user' ? [] : [$user->role]);

        if ($user->role === 'user') {
            $user->syncPermissions([]);
        }
    }

    private function summarize(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'permissions' => $user->hasRole('owner')
                ? RolePermissions::ALL
                : $user->getDirectPermissions()->pluck('name')->values()->all(),
            'createdAt' => $user->created_at?->format('Y-m-d'),
        ];
    }
}
