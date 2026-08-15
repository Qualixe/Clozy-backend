<?php

namespace App\Http\Controllers;

use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
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

        // Keyed by email, not IP — trustProxies(at: '*') means the request
        // IP can be reset at will by spoofing X-Forwarded-For, which would
        // give an IP-keyed lock a fresh bucket on every attempt. A per-
        // account key can't be sidestepped that way.
        $throttleKey = 'login:' . strtolower($validated['email']);

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

    /** Self-service profile edit — the storefront's Profile page. */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'phone' => ['nullable', 'string', 'max:32'],
            'dob' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:1000'],
            'district' => ['nullable', 'string', 'max:255'],
        ]);

        $emailChanged = $validated['email'] !== $user->email;
        $phoneChanged = ($validated['phone'] ?? null) !== $user->phone;

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
        $user->gender = $validated['gender'] ?? null;
        $user->phone = $validated['phone'] ?? null;
        // Same logic as email: a changed number hasn't been proven to
        // belong to this person — see confirmPhone() for how it's
        // re-verified via OTP.
        if ($phoneChanged) {
            $user->phone_verified_at = null;
        }
        $user->dob = $validated['dob'] ?? null;
        $user->address = $validated['address'] ?? null;
        $user->district = $validated['district'] ?? null;
        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json($this->summarize($user));
    }

    /**
     * Confirms the signed-in user's phone number, using the same OTP flow
     * checkout uses (`PhoneVerificationController`) — the frontend calls
     * `/checkout/phone/send-code` then `/checkout/phone/verify-code` first,
     * then this to persist the result onto the account. Kept separate from
     * `updateProfile()` so a plain profile save can't set phone_verified_at
     * by itself.
     */
    public function confirmPhone(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'phone' => ['required', 'regex:/^01[3-9]\d{8}$/'],
        ]);

        $recentlyVerified = PhoneVerification::where('phone', $validated['phone'])
            ->whereNotNull('verified_at')
            ->where('verified_at', '>', now()->subMinutes(10))
            ->exists();

        if (! $recentlyVerified) {
            return response()->json([
                'message' => 'Please verify this phone number first.',
            ], 422);
        }

        $user->phone = $validated['phone'];
        $user->phone_verified_at = now();
        $user->save();

        return response()->json($this->summarize($user));
    }

    /**
     * Uploads (or replaces) the signed-in user's own profile photo. Kept
     * separate from `/media` (the CMS media library) since that's staff-only
     * (`create_media`) — this is scoped to updating your own account, no
     * permission beyond being logged in.
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,gif'],
        ]);

        $oldPath = $user->avatar_url ? $this->avatarPathFromUrl($user->avatar_url) : null;

        $path = $validated['file']->store('media', 'public');
        $user->avatar_url = url('/api/media/file/'.basename($path));
        $user->save();

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json($this->summarize($user));
    }

    private function avatarPathFromUrl(string $url): string
    {
        return 'media/'.basename($url);
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
            'avatarUrl' => $user->avatar_url,
            'gender' => $user->gender,
            'phone' => $user->phone,
            'phoneVerified' => $user->phone !== null && $user->phone_verified_at !== null,
            'dob' => $user->dob?->toDateString(),
            'address' => $user->address,
            'district' => $user->district,
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
