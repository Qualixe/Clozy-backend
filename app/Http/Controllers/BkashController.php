<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\BkashService;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BkashController extends Controller
{
    public function __construct(
        private readonly BkashService $bkash,
        private readonly SmsService $sms,
    ) {}

    /**
     * Starts a bKash payment session for an already-created order (see
     * OrderController::store — the order exists first, in "processing" /
     * payment_status "pending" state, before the customer ever reaches
     * bKash). Returns the URL the frontend should redirect to.
     */
    public function create(string $id): JsonResponse
    {
        $order = Order::find($id);

        if (! $order || ! $order->isBkashFamily()) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'This order has already been paid.'], 422);
        }

        try {
            $result = $this->bkash->create($order, route('bkash.callback'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->update([
            'bkash_payment_id' => $result['payment_id'],
            'payment_status' => 'pending',
        ]);

        return response()->json(['bkashUrl' => $result['bkash_url']]);
    }

    /**
     * bKash redirects the customer's browser here after they complete (or
     * abandon) payment on bKash's own page — `status` is theirs, not ours:
     * success | failure | cancel. We only call execute() (which actually
     * captures the payment) when they report success.
     */
    public function callback(Request $request): RedirectResponse
    {
        $paymentId = $request->query('paymentID');
        $status = $request->query('status');
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        $order = Order::where('bkash_payment_id', $paymentId)->first();

        if (! $order) {
            return redirect("{$frontendUrl}/checkout/bkash-result?status=failed");
        }

        if ($status !== 'success') {
            $order->update(['payment_status' => 'failed']);

            return redirect("{$frontendUrl}/checkout/bkash-result?order={$order->order_number}&status=failed");
        }

        try {
            $result = $this->bkash->execute($paymentId);
        } catch (RuntimeException $e) {
            $order->update(['payment_status' => 'failed']);

            return redirect("{$frontendUrl}/checkout/bkash-result?order={$order->order_number}&status=failed");
        }

        $order->update([
            'payment_status' => 'paid',
            'bkash_trx_id' => $result['trx_id'],
        ]);

        $this->sms->sendOrderConfirmation($order);

        return redirect("{$frontendUrl}/checkout/bkash-result?order={$order->order_number}&status=paid");
    }
}
