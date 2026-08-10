<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\SteadfastService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class CourierController extends Controller
{
    public function __construct(private readonly SteadfastService $steadfast) {}

    /** Creates a Steadfast consignment for this order — the "Send to Steadfast" button. */
    public function send(string $id): JsonResponse
    {
        $order = Order::find($id);

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->steadfast_consignment_id) {
            return response()->json(['message' => 'This order has already been sent to Steadfast.'], 422);
        }

        try {
            $result = $this->steadfast->createOrder($order);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->update([
            'steadfast_consignment_id' => $result['consignment_id'],
            'steadfast_tracking_code' => $result['tracking_code'],
            'steadfast_status' => $result['status'],
        ]);

        return response()->json($this->summarize($order));
    }

    /** Refreshes the delivery status of an already-shipped order. */
    public function refreshStatus(string $id): JsonResponse
    {
        $order = Order::find($id);

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if (! $order->steadfast_tracking_code) {
            return response()->json(['message' => 'This order has not been sent to Steadfast yet.'], 422);
        }

        try {
            $status = $this->steadfast->trackByTrackingCode($order->steadfast_tracking_code);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->update(['steadfast_status' => $status]);

        return response()->json($this->summarize($order));
    }

    /** The store's current Steadfast account balance — Settings > Shipping. */
    public function balance(): JsonResponse
    {
        try {
            return response()->json(['balance' => $this->steadfast->getBalance()]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function summarize(Order $order): array
    {
        return [
            'steadfastConsignmentId' => $order->steadfast_consignment_id,
            'steadfastTrackingCode' => $order->steadfast_tracking_code,
            'steadfastStatus' => $order->steadfast_status,
        ];
    }
}
