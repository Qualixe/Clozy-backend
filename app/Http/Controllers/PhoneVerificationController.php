<?php

namespace App\Http\Controllers;

use App\Models\PhoneVerification;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Checkout's phone OTP step — fake-order protection. A storefront order
 * can't be placed (see OrderController::store()) until its contact phone
 * number has a recent, verified row here. Dashboard-created (staff) orders
 * skip this entirely — see the auth check in store().
 */
class PhoneVerificationController extends Controller
{
    private const CODE_LIFETIME_MINUTES = 5;

    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly SmsService $sms) {}

    public function sendCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'regex:/^01[3-9]\d{8}$/'],
        ]);

        $phone = $validated['phone'];
        $throttleKey = 'otp-send:' . $phone;

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => 'Too many codes requested. Please try again in ' . ceil($seconds / 60) . ' minute(s).',
            ], 429);
        }

        RateLimiter::hit($throttleKey, 600);

        // Old, still-pending codes for this number are void once a new one
        // is requested — only the latest code should ever work.
        PhoneVerification::where('phone', $phone)->whereNull('verified_at')->delete();

        $code = (string) random_int(100000, 999999);

        PhoneVerification::create([
            'phone' => $phone,
            'code' => $code,
            'expires_at' => now()->addMinutes(self::CODE_LIFETIME_MINUTES),
        ]);

        $this->sms->sendOtp($phone, $code);

        return response()->json(['message' => 'Verification code sent.']);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'regex:/^01[3-9]\d{8}$/'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $throttleKey = 'otp-verify:' . $validated['phone'];

        if (RateLimiter::tooManyAttempts($throttleKey, 8)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => 'Too many attempts. Please try again in ' . ceil($seconds / 60) . ' minute(s).',
            ], 429);
        }

        RateLimiter::hit($throttleKey, 600);

        $verification = PhoneVerification::where('phone', $validated['phone'])
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $verification) {
            return response()->json([
                'message' => "That code has expired. Please request a new one.",
            ], 422);
        }

        if (! hash_equals($verification->code, $validated['code'])) {
            $verification->increment('attempts');

            if ($verification->attempts >= self::MAX_ATTEMPTS) {
                $verification->delete();

                return response()->json([
                    'message' => 'Too many incorrect attempts. Please request a new code.',
                ], 422);
            }

            return response()->json(['message' => 'Incorrect code.'], 422);
        }

        $verification->update(['verified_at' => now()]);

        return response()->json(['message' => 'Phone number verified.']);
    }
}
