<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = Order::query()->orderByDesc('created_at')->get();

        return response()->json($orders->map(fn (Order $o) => $this->summarize($o))->values());
    }

    public function show(string $id): JsonResponse
    {
        $order = Order::with('items')->find($id);

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json($this->detail($order));
    }

    /**
     * Powers checkout — accepts the cart + shipping form as submitted by
     * the checkout page and creates a real order with line-item snapshots.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['required', 'email', 'max:255'],
            'address' => ['required', 'string'],
            'district' => ['required', 'string', 'max:255'],
            'paymentMethod' => ['required', 'in:cod,bkash'],
            'bkashNumber' => ['nullable', 'string', 'max:32'],
            'shippingCost' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['nullable'],
            'items.*.name' => ['required', 'string'],
            'items.*.variant' => ['nullable', 'string'],
            'items.*.image' => ['nullable', 'string'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        $order = DB::transaction(function () use ($validated) {
            $subtotal = collect($validated['items'])
                ->sum(fn ($item) => $item['price'] * $item['qty']);
            $shipping = (float) $validated['shippingCost'];

            $order = Order::create([
                'order_number' => $this->nextOrderNumber(),
                'customer_name' => $validated['name'],
                'customer_email' => $validated['email'],
                'customer_phone' => $validated['phone'],
                'address' => $validated['address'],
                'district' => $validated['district'],
                'subtotal' => $subtotal,
                'shipping_cost' => $shipping,
                'total' => $subtotal + $shipping,
                'payment_method' => $validated['paymentMethod'],
                'bkash_number' => $validated['paymentMethod'] === 'bkash'
                    ? ($validated['bkashNumber'] ?? null)
                    : null,
                'status' => 'processing',
            ]);

            foreach ($validated['items'] as $item) {
                $order->items()->create([
                    'product_id' => is_numeric($item['productId'] ?? null)
                        ? (int) $item['productId']
                        : null,
                    'name' => $item['name'],
                    'variant' => $item['variant'] ?? null,
                    'image' => $item['image'] ?? null,
                    'price' => $item['price'],
                    'qty' => $item['qty'],
                ]);
            }

            return $order;
        });

        return response()->json($this->detail($order->load('items')), 201);
    }

    /**
     * The core "management" action — move an order through its lifecycle.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $order = Order::find($id);

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:processing,fulfilled,cancelled'],
        ]);

        $order->update(['status' => $validated['status']]);

        return response()->json($this->summarize($order));
    }

    private function nextOrderNumber(): string
    {
        $last = (int) (Order::max('id') ?? 0);

        return (string) (1000 + $last + 1);
    }

    private function summarize(Order $order): array
    {
        return [
            'id' => (string) $order->id,
            'orderNumber' => '#'.$order->order_number,
            'customer' => $order->customer_name,
            'email' => $order->customer_email,
            'status' => ucfirst($order->status),
            'payment' => $order->payment_method === 'bkash' ? 'bKash' : 'COD',
            'total' => (float) $order->total,
            'date' => $order->created_at->format('Y-m-d'),
        ];
    }

    private function detail(Order $order): array
    {
        return [
            ...$this->summarize($order),
            'phone' => $order->customer_phone,
            'address' => $order->address,
            'district' => $order->district,
            'subtotal' => (float) $order->subtotal,
            'shippingCost' => (float) $order->shipping_cost,
            'bkashNumber' => $order->bkash_number,
            'items' => $order->items->map(fn ($item) => [
                'id' => (string) $item->id,
                'name' => $item->name,
                'variant' => $item->variant,
                'image' => $item->image,
                'price' => (float) $item->price,
                'qty' => $item->qty,
            ])->values(),
        ];
    }
}
