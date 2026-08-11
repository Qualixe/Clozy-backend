<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\PathaoService;
use App\Services\SteadfastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CourierController extends Controller
{
    public function __construct(
        private readonly SteadfastService $steadfast,
        private readonly PathaoService $pathao,
    ) {}

    /** Creates a consignment for this order via the chosen courier. */
    public function send(Request $request, string $id): JsonResponse
    {
        $order = Order::find($id);

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->courier_consignment_id) {
            return response()->json(['message' => 'This order has already been sent to a courier.'], 422);
        }

        $validated = $request->validate([
            'courier' => ['required', 'in:steadfast,pathao'],
            'cityId' => ['required_if:courier,pathao', 'integer'],
            'zoneId' => ['required_if:courier,pathao', 'integer'],
        ]);

        try {
            $result = $validated['courier'] === 'pathao'
                ? $this->pathao->createOrder($order, (int) $validated['cityId'], (int) $validated['zoneId'])
                : $this->steadfast->createOrder($order);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->update([
            'courier' => $validated['courier'],
            'courier_consignment_id' => $result['consignment_id'],
            'courier_tracking_code' => $result['tracking_code'] ?? $result['consignment_id'],
            'courier_status' => $result['status'],
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

        if (! $order->courier_consignment_id) {
            return response()->json(['message' => 'This order has not been sent to a courier yet.'], 422);
        }

        try {
            $status = $order->courier === 'pathao'
                ? $this->pathao->trackOrder($order->courier_consignment_id)
                : $this->steadfast->trackByTrackingCode($order->courier_tracking_code);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $order->update(['courier_status' => $status]);

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

    public function pathaoCities(): JsonResponse
    {
        try {
            return response()->json(['cities' => $this->pathao->getCities()]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function pathaoZones(string $cityId): JsonResponse
    {
        try {
            return response()->json(['zones' => $this->pathao->getZones((int) $cityId)]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function pathaoStores(): JsonResponse
    {
        try {
            return response()->json(['stores' => $this->pathao->getStores()]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function summarize(Order $order): array
    {
        return [
            'courier' => $order->courier,
            'courierConsignmentId' => $order->courier_consignment_id,
            'courierTrackingCode' => $order->courier_tracking_code,
            'courierStatus' => $order->courier_status,
        ];
    }
}
