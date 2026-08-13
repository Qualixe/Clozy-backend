<?php

namespace App\Services;

use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pathao Courier integration. Unlike Steadfast, Pathao requires:
 *  - an OAuth2 (password grant) access token, cached on StoreSetting and
 *    refreshed by re-authenticating once it expires (tokens are long-lived
 *    — ~90 days — so a full refresh-token flow wasn't worth the extra
 *    complexity);
 *  - a registered `store_id` (fetched via getStores() and picked by the
 *    admin in Settings, not something we can infer);
 *  - Pathao's own numeric city_id/zone_id for the recipient, since orders
 *    only store a free-text district — the admin picks city/zone in the
 *    "Send to Pathao" dialog at ship time.
 *
 * Every endpoint shape below (issue-token, city-list, zone-list, stores,
 * orders, orders/{id}/info) was verified live against the real sandbox
 * (courier-api-sandbox.pathao.com) while building this integration.
 */
class PathaoService
{
    public function isConfigured(): bool
    {
        $settings = StoreSetting::current();

        return $settings->pathao_enabled
            && ! empty($settings->pathao_base_url)
            && ! empty($settings->pathao_client_id)
            && ! empty($settings->pathao_client_secret)
            && ! empty($settings->pathao_username)
            && ! empty($settings->pathao_password);
    }

    public function getCities(): array
    {
        $data = $this->get('/aladdin/api/v1/city-list');

        return collect($data['data']['data'] ?? [])
            ->map(fn ($c) => ['cityId' => $c['city_id'], 'cityName' => trim($c['city_name'])])
            ->values()->all();
    }

    public function getZones(int $cityId): array
    {
        $data = $this->get("/aladdin/api/v1/cities/{$cityId}/zone-list");

        return collect($data['data']['data'] ?? [])
            ->map(fn ($z) => ['zoneId' => $z['zone_id'], 'zoneName' => trim($z['zone_name'])])
            ->values()->all();
    }

    public function getStores(): array
    {
        $data = $this->get('/aladdin/api/v1/stores');

        return collect($data['data']['data'] ?? [])
            ->map(fn ($s) => ['storeId' => $s['store_id'], 'storeName' => $s['store_name']])
            ->values()->all();
    }

    /** Creates a consignment for this order. Requires an admin-picked recipient city/zone. */
    public function createOrder(Order $order, int $cityId, int $zoneId): array
    {
        $settings = StoreSetting::current();

        if (empty($settings->pathao_store_id)) {
            throw new RuntimeException('No Pathao store selected. Pick one in Settings > Shipping.');
        }

        $response = $this->client()->post($this->url('/aladdin/api/v1/orders'), [
            'store_id' => (int) $settings->pathao_store_id,
            'merchant_order_id' => $order->order_number,
            'recipient_name' => $order->customer_name,
            'recipient_phone' => $order->customer_phone,
            'recipient_address' => trim(collect([$order->address, $order->district])->filter()->implode(', ')),
            'recipient_city' => $cityId,
            'recipient_zone' => $zoneId,
            'delivery_type' => 48, // Normal delivery (48hr), vs. 12 = on-demand.
            'item_type' => 2, // Parcel, vs. 1 = document.
            'special_instruction' => 'Clozy order #'.$order->order_number,
            'item_quantity' => max(1, $order->items()->count()),
            'item_weight' => 0.5,
            'amount_to_collect' => $order->codAmountDue(),
            'item_description' => 'Clozy order #'.$order->order_number,
        ]);

        $body = $response->json();

        if (! $response->successful() || empty($body['data']['consignment_id'])) {
            throw new RuntimeException($body['message'] ?? 'Pathao rejected the request: '.substr((string) $response->body(), 0, 500));
        }

        return [
            'consignment_id' => (string) $body['data']['consignment_id'],
            'status' => (string) ($body['data']['order_status'] ?? 'Pending'),
        ];
    }

    public function trackOrder(string $consignmentId): string
    {
        $data = $this->get("/aladdin/api/v1/orders/{$consignmentId}/info");

        return (string) ($data['data']['order_status'] ?? 'unknown');
    }

    private function get(string $path): array
    {
        $response = $this->client()->get($this->url($path));

        if (! $response->successful()) {
            throw new RuntimeException($response->json('message') ?? 'Could not reach Pathao.');
        }

        return $response->json();
    }

    private function client()
    {
        return Http::withToken($this->getAccessToken())->acceptJson()->timeout(15);
    }

    private function url(string $path): string
    {
        $settings = StoreSetting::current();

        return rtrim((string) $settings->pathao_base_url, '/').$path;
    }

    private function getAccessToken(): string
    {
        $settings = StoreSetting::current();

        if (! $this->isConfigured()) {
            throw new RuntimeException('Pathao is not configured. Add your credentials in Settings > Shipping.');
        }

        if ($settings->pathao_access_token && $settings->pathao_token_expires_at?->isFuture()) {
            return $settings->pathao_access_token;
        }

        $response = Http::acceptJson()->timeout(15)->post($this->url('/aladdin/api/v1/issue-token'), [
            'client_id' => $settings->pathao_client_id,
            'client_secret' => $settings->pathao_client_secret,
            'username' => $settings->pathao_username,
            'password' => $settings->pathao_password,
            'grant_type' => 'password',
        ]);

        $body = $response->json();

        if (! $response->successful() || empty($body['access_token'])) {
            throw new RuntimeException($body['message'] ?? 'Could not authenticate with Pathao — check your credentials.');
        }

        $settings->update([
            'pathao_access_token' => $body['access_token'],
            'pathao_refresh_token' => $body['refresh_token'] ?? null,
            'pathao_token_expires_at' => now()->addSeconds(($body['expires_in'] ?? 3600) - 60),
        ]);

        return $body['access_token'];
    }
}
