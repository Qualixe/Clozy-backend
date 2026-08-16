<?php

namespace App\Support;

/**
 * Turns a raw AI-provider error (an HTTP status code, or a thrown
 * exception) into something worth showing a store owner or a customer —
 * every AI feature (ChatController, AnalyticsController,
 * AdminChatController) was instead surfacing the provider's raw JSON error
 * body verbatim, which is unreadable and rarely tells you what to actually
 * do about it.
 */
class AiErrorMessage
{
    public static function forStatus(int $status, string $providerLabel): string
    {
        return match (true) {
            $status === 401 || $status === 403 => "Your {$providerLabel} API key looks invalid, expired, or doesn't have access to this — double-check it in Settings > AI.",
            $status === 404 => "The {$providerLabel} model configured in Settings > AI isn't available for your account. Try a different model there.",
            $status === 429 => "{$providerLabel} says you've hit a rate limit or usage quota. Wait a moment and try again, or check your plan/billing on {$providerLabel}'s side.",
            $status >= 500 => "{$providerLabel} is having trouble on their end right now — please try again in a bit.",
            default => "{$providerLabel} couldn't process that request right now. Please try again, or try a different provider in Settings > AI.",
        };
    }

    /** For a network-level failure (timeout, DNS, connection refused — never actually reached the provider). */
    public static function forConnectionFailure(string $providerLabel): string
    {
        return "Couldn't reach {$providerLabel} right now — this is usually temporary. Please try again in a moment.";
    }
}
