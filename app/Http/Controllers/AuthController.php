<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class AuthController extends Controller
{
    /** After this many failed attempts from the same IP, further logins are blocked. */
    private const MAX_LOGIN_ATTEMPTS = 5;

    /** How long an IP stays blocked once it hits the attempt limit. */
    private const LOGIN_LOCKOUT_SECONDS = 15 * 60;

    /** Customer signup — always created as a plain "user", never a staff role. */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'user',
        ]);

        $user->sendEmailVerificationNotification();

        $token = $user->createToken('storefront')->plainTextToken;

        return response()->json([
            'user' => $this->summarize($user),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Please try again in " . ceil($seconds / 60) . ' minute(s).'],
            ]);
        }

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey, self::LOGIN_LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'email' => ['Those credentials do not match our records.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        $token = $user->createToken('storefront')->plainTextToken;

        return response()->json([
            'user' => $this->summarize($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->summarize($request->user()));
    }

    /** Self-service profile edit — the storefront's Account > Settings page. */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $emailChanged = $validated['email'] !== $user->email;

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        // A changed email hasn't been proven to belong to this person yet —
        // require it to be verified again, same as the address on signup.
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }
        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json($this->summarize($user));
    }

    /**
     * The link a verification email sends the user to. Deliberately doesn't
     * require `auth:sanctum` — the browser opens this straight from the
     * email with no bearer token attached, so the signed URL itself (see
     * the `signed` route middleware) is the proof of identity.
     */
    public function verifyEmail(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::find($id);
        $frontendUrl = config('app.frontend_url');

        if (! $user || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect("{$frontendUrl}/email-verified?error=invalid");
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect("{$frontendUrl}/email-verified");
    }

    /** Lets a signed-in but unverified user request a fresh verification email. */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }

    private function summarize(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $this->permissionNamesFor($user),
            'emailVerified' => $user->hasVerifiedEmail(),
        ];
    }

    /**
     * Owner bypasses every permission check via Gate::before (see
     * AppServiceProvider) rather than holding explicit permission rows, so
     * it's reported here as having all of them — this only feeds frontend
     * UI gating, the real enforcement stays server-side regardless of what
     * this array says.
     */
    private function permissionNamesFor(User $user): array
    {
        if ($user->hasRole('owner')) {
            return Permission::pluck('name')->values()->all();
        }

        return $user->getAllPermissions()->pluck('name')->values()->all();
    }
}
