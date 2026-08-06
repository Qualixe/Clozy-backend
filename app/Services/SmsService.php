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
 * configured in the dashboard's Settings > SMS tab). The exact request
 * shape below (JSON body: api_key/senderid/number/message) is a common
 * pattern for Bangladeshi providers (SSL Wireless, BulkSMSBD, Alpha SMS,
 * ...) but not universal — adjust the `send()` payload to match whichever
 * provider you're actually using.
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
            $response = Http::timeout(10)->asForm()->post($settings->sms_gateway_url, [
                'api_key' => $settings->sms_api_key,
                'senderid' => $settings->sms_sender_id,
                'number' => $recipient,
                'message' => $message,
            ]);

            return SmsLog::create([
                'order_id' => $order?->id,
                'type' => $type,
                'recipient' => $recipient,
                'message' => $message,
                'status' => $response->successful() ? 'sent' : 'failed',
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

    private function renderTemplate(string $template, Order $order): string
    {
        return strtr($template, [
            '{customer_name}' => $order->customer_name,
            '{order_number}' => '#'.$order->order_number,
            '{total}' => number_format((float) $order->total, 2),
        ]);
    }
}
