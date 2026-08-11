<?php

namespace App\Services;

use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * bKash Tokenized Checkout (PGW) integration — v1.2.0-beta. Flow:
 *  1. create()  — POST /tokenized/checkout/create, returns a `bkashURL` the
 *     customer is redirected to (bKash's own hosted payment page).
 *  2. bKash redirects the browser back to our callback URL with
 *     `?paymentID=...&status=success|failure|cancel` appended.
 *  3. On status=success, execute() confirms/captures the payment.
 *
 * Sandbox base URL: https://tokenized.sandbox.bka.sh/v1.2.0-beta
 * Production base URL: https://tokenized.pay.bka.sh/v1.2.0-beta
 * Credentials (app key/secret, username/password) come from the dashboard's
 * Settings > Payment tab, same pattern as the Steadfast/Pathao couriers.
 *
 * Built from bKash's published API documentation — not yet exercised
 * against a live sandbox account (no credentials were available while
 * building this). The first real create() call is effectively the
 * integration test; RuntimeExceptions below surface bKash's raw response
 * message to make any field-name mismatch easy to spot and fix.
 */
class BkashService
{
    public function isConfigured(): bool
    {
        $s = StoreSetting::current();

        return $s->bkash_gateway_enabled
            && ! empty($s->bkash_base_url)
            && ! empty($s->bkash_app_key)
            && ! empty($s->bkash_app_secret)
            && ! empty($s->bkash_username)
            && ! empty($s->bkash_password);
    }

    /** Starts a payment session. Returns the paymentID and the URL to redirect the customer to. */
    public function create(Order $order, string $callbackUrl): array
    {
        $response = $this->client()->post($this->url('/tokenized/checkout/create'), [
            'mode' => '0011',
            'payerReference' => $order->customer_phone ?: $order->order_number,
            'callbackURL' => $callbackUrl,
            'amount' => number_format((float) $order->total, 2, '.', ''),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => $order->order_number,
        ]);

        $body = $response->json();

        // Belt-and-braces, same lesson learned from BulkSMSBD: don't trust
        // the HTTP status alone if the API also returns its own status
        // code — require both, when statusCode is present at all.
        $statusOk = ! isset($body['statusCode']) || $body['statusCode'] === '0000';

        if (! $response->successful() || ! $statusOk || empty($body['paymentID']) || empty($body['bkashURL'])) {
            throw new RuntimeException($body['statusMessage'] ?? 'bKash rejected the payment request: '.substr((string) $response->body(), 0, 500));
        }

        return [
            'payment_id' => $body['paymentID'],
            'bkash_url' => $body['bkashURL'],
        ];
    }

    /** Confirms/captures a payment after the customer completes it on bKash's page. */
    public function execute(string $paymentId): array
    {
        $response = $this->client()->post($this->url('/tokenized/checkout/execute'), [
            'paymentID' => $paymentId,
        ]);

        $body = $response->json();
        $status = $body['transactionStatus'] ?? null;
        $statusOk = ! isset($body['statusCode']) || $body['statusCode'] === '0000';

        if (! $response->successful() || ! $statusOk || $status !== 'Completed') {
            throw new RuntimeException($body['statusMessage'] ?? 'Payment could not be confirmed.');
        }

        return [
            'trx_id' => $body['trxID'] ?? null,
            'status' => $status,
        ];
    }

    private function client()
    {
        return Http::withHeaders([
            'Authorization' => $this->getToken(),
            'X-APP-Key' => StoreSetting::current()->bkash_app_key,
            'Content-Type' => 'application/json',
        ])->acceptJson()->timeout(15);
    }

    private function url(string $path): string
    {
        return rtrim((string) StoreSetting::current()->bkash_base_url, '/').$path;
    }

    private function getToken(): string
    {
        $s = StoreSetting::current();

        if (! $this->isConfigured()) {
            throw new RuntimeException('bKash is not configured. Add your credentials in Settings > Payment.');
        }

        if ($s->bkash_id_token && $s->bkash_token_expires_at?->isFuture()) {
            return $s->bkash_id_token;
        }

        $response = Http::withHeaders([
            'username' => $s->bkash_username,
            'password' => $s->bkash_password,
            'Content-Type' => 'application/json',
        ])->acceptJson()->timeout(15)->post($this->url('/tokenized/checkout/token/grant'), [
            'app_key' => $s->bkash_app_key,
            'app_secret' => $s->bkash_app_secret,
        ]);

        $body = $response->json();

        if (! $response->successful() || empty($body['id_token'])) {
            throw new RuntimeException($body['statusMessage'] ?? 'Could not authenticate with bKash — check your credentials.');
        }

        $s->update([
            'bkash_id_token' => $body['id_token'],
            'bkash_refresh_token' => $body['refresh_token'] ?? null,
            'bkash_token_expires_at' => now()->addSeconds(((int) ($body['expires_in'] ?? 3600)) - 60),
        ]);

        return $body['id_token'];
    }
}
