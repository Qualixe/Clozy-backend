<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SmsLog;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends SMS through a generic HTTP gateway (URL + API key + sender ID,
 * configured in the dashboard's Settings > SMS tab). The request shape
 * below (query params: api_key/type/number/senderid/message) matches
 * BulkSMSBD's API exactly and is close to several other Bangladeshi
 * providers (SSL Wireless, Alpha SMS, ...) — adjust `send()` if you're on
 * a different one.
 */
class SmsService
{
    public function sendOrderConfirmation(Order $order): void
    {
        $settings = StoreSetting::current();

        if (! $settings->sms_order_confirmation_enabled || ! $order->customer_phone) {
            return;
        }

        $message = $this->renderTemplate(
            $settings->sms_order_confirmation_template ?: $this->defaultConfirmationTemplate(),
            $order
        );

        $this->send($order->customer_phone, $message, 'order_confirmation', $order);
    }

    /** A one-time checkout verification code — see PhoneVerificationController. */
    public function sendOtp(string $phone, string $code): void
    {
        $this->send($phone, "Your Clozy verification code is {$code}. It expires in 5 minutes.", 'phone_verification', null);
    }

    public function sendOrderCancelled(Order $order): void
    {
        $settings = StoreSetting::current();

        if (! $settings->sms_order_cancelled_enabled || ! $order->customer_phone) {
            return;
        }

        $message = $this->renderTemplate(
            $settings->sms_order_cancelled_template ?: $this->defaultCancelledTemplate(),
            $order
        );

        $this->send($order->customer_phone, $message, 'order_cancelled', $order);
    }

    /**
     * Sends the same message to many numbers, logging each attempt
     * separately. Returns the created SmsLog rows.
     *
     * @param  string[]  $recipients
     * @return SmsLog[]
     */
    public function sendPromotional(array $recipients, string $message): array
    {
        return array_map(
            fn (string $recipient) => $this->send($recipient, $message, 'promotional', null),
            $recipients
        );
    }

    public function defaultConfirmationTemplate(): string
    {
        return 'Hi {customer_name}, your order {order_number} has been confirmed. Total: {total}. Thank you for shopping with us!';
    }

    public function defaultCancelledTemplate(): string
    {
        return 'Hi {customer_name}, your order {order_number} has been cancelled. Contact us if you have questions.';
    }

    /**
     * Always returns (never throws) — a broken/misconfigured gateway must
     * never break order creation or status updates, it just logs 'failed'.
     */
    private function send(string $recipient, string $message, string $type, ?Order $order): SmsLog
    {
        $settings = StoreSetting::current();

        if (! $settings->sms_gateway_url || ! $settings->sms_api_key) {
            return SmsLog::create([
                'order_id' => $order?->id,
                'type' => $type,
                'recipient' => $recipient,
                'message' => $message,
                'status' => 'failed',
                'response' => 'SMS gateway is not configured (missing URL or API key).',
            ]);
        }

        try {
            // BulkSMSBD's API (and most similar BD gateways) reads its
            // params from the query string — matches their documented
            // "API URL (GET & POST)" example exactly. The `number` param
            // must be in international form (8801XXXXXXXXX, no `+`) — a
            // bare local 01XXXXXXXXX is silently undeliverable.
            $response = Http::timeout(10)->get($settings->sms_gateway_url, [
                'api_key' => $settings->sms_api_key,
                'type' => 'text',
                'number' => $this->toInternational($recipient),
                'senderid' => $settings->sms_sender_id,
                'message' => $message,
            ]);

            // BulkSMSBD always answers HTTP 200, even on failure (bad API
            // key, un-whitelisted IP, etc.) — the real result is
            // response_code 202 inside the JSON body, so the HTTP status
            // alone isn't a reliable signal.
            $responseCode = $response->json('response_code');
            $delivered = $response->successful() && ((int) $responseCode === 202 || $responseCode === null);

            return SmsLog::create([
                'order_id' => $order?->id,
                'type' => $type,
                'recipient' => $recipient,
                'message' => $message,
                'status' => $delivered ? 'sent' : 'failed',
                'response' => substr((string) $response->body(), 0, 2000),
            ]);
        } catch (Throwable $e) {
            Log::warning('SMS send failed', ['recipient' => $recipient, 'error' => $e->getMessage()]);

            return SmsLog::create([
                'order_id' => $order?->id,
                'type' => $type,
                'recipient' => $recipient,
                'message' => $message,
                'status' => 'failed',
                'response' => $e->getMessage(),
            ]);
        }
    }

    /** Local 01XXXXXXXXX -> international 8801XXXXXXXXX, as BulkSMSBD expects. */
    private function toInternational(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '880')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '880'.substr($digits, 1);
        }

        return '880'.$digits;
    }

    private function renderTemplate(string $template, Order $order): string
    {
        return strtr($template, [
            '{customer_name}' => $order->customer_name,
            '{order_number}' => '#'.$order->order_number,
            '{total}' => number_format((float) $order->total, 2),
        ]);
    }
}
