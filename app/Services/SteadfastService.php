<?php

namespace App\Services;

use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Steadfast Courier (portal.packzy.com) integration — creates a delivery
 * consignment for an order and checks its status. Credentials (Api-Key /
 * Secret-Key) come from the dashboard's Settings > Shipping tab, same
 * pattern as SmsService reading StoreSetting.
 *
 * Request/response shapes here match Steadfast's published API docs. Since
 * this was built before real credentials were available, treat the first
 * live `createOrder()` call as the integration test — if a field name is
 * off, the raw response body is surfaced in the thrown exception message
 * to make that easy to spot and fix.
 */
class SteadfastService
{
    private const BASE_URL = 'https://portal.packzy.com/api/v1';

    public function isConfigured(): bool
    {
        $settings = StoreSetting::current();

        return $settings->steadfast_enabled
            && ! empty($settings->steadfast_api_key)
            && ! empty($settings->steadfast_secret_key);
    }

    /**
     * Creates a consignment for this order. Returns the fields we persist
     * on the order. Throws with a clear message on any failure — this is a
     * deliberate, user-triggered action ("Send to Steadfast"), not a
     * best-effort background job, so the dashboard should show the real
     * error rather than swallowing it.
     */
    public function createOrder(Order $order): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Steadfast is not configured. Add your API key and secret key in Settings > Shipping.');
        }

        $response = $this->client()->post(self::BASE_URL.'/create_order', [
            'invoice' => $order->order_number,
            'recipient_name' => $order->customer_name,
            'recipient_phone' => $order->customer_phone,
            'recipient_address' => trim(collect([$order->address, $order->district])->filter()->implode(', ')),
            'cod_amount' => $order->payment_method === 'cod' ? (float) $order->total : 0,
            'note' => 'Clozy order #'.$order->order_number,
        ]);

        $body = $response->json();
        $consignment = $body['consignment'] ?? null;

        if (! $response->successful() || ! $consignment) {
            throw new RuntimeException(
                $body['message'] ?? 'Steadfast rejected the request: '.substr((string) $response->body(), 0, 500)
            );
        }

        return [
            'consignment_id' => (string) $consignment['consignment_id'],
            'tracking_code' => (string) $consignment['tracking_code'],
            'status' => (string) ($consignment['status'] ?? 'in_review'),
        ];
    }

    /** Re-checks a previously created consignment's current delivery status. */
    public function trackByTrackingCode(string $trackingCode): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Steadfast is not configured.');
        }

        $response = $this->client()->get(self::BASE_URL.'/status_by_trackingcode/'.$trackingCode);
        $body = $response->json();

        if (! $response->successful()) {
            throw new RuntimeException($body['message'] ?? 'Could not fetch tracking status from Steadfast.');
        }

        return (string) ($body['delivery_status'] ?? 'unknown');
    }

    /** The store's current Steadfast account balance — shown in Settings. */
    public function getBalance(): float
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Steadfast is not configured.');
        }

        $response = $this->client()->get(self::BASE_URL.'/get_balance');
        $body = $response->json();

        if (! $response->successful()) {
            throw new RuntimeException($body['message'] ?? 'Could not fetch balance from Steadfast.');
        }

        return (float) ($body['current_balance'] ?? 0);
    }

    private function client()
    {
        $settings = StoreSetting::current();

        return Http::withHeaders([
            'Api-Key' => $settings->steadfast_api_key,
            'Secret-Key' => $settings->steadfast_secret_key,
            'Content-Type' => 'application/json',
        ])->timeout(15);
    }
}
