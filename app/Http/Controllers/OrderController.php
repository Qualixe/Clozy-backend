<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use App\Models\Order;
use App\Models\PhoneVerification;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class OrderController extends Controller
{
    public function __construct(private readonly SmsService $sms) {}

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
     * The signed-in customer's own order history — matched by email rather
     * than a `user_id` column, since guest checkout orders share the same
     * lookup key. Storefront Account > Orders page.
     */
    public function myOrders(Request $request): JsonResponse
    {
        $orders = Order::where('customer_email', $request->user()->email)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($orders->map(fn (Order $o) => $this->summarize($o))->values());
    }

    /**
     * A single order's detail for the signed-in customer — scoped to their
     * own email so one account can't view another's order by guessing IDs.
     */
    public function myOrder(Request $request, string $id): JsonResponse
    {
        $order = Order::with('items')
            ->where('id', $id)
            ->where('customer_email', $request->user()->email)
            ->first();

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json($this->detail($order));
    }

    /**
     * Public order lookup for the storefront's Track Order page. Requires
     * both the order number and the email/phone on file — matching on the
     * order number alone would let anyone page through every order.
     */
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orderNumber' => ['required', 'string'],
            'contact' => ['required', 'string'],
        ]);

        $orderNumber = ltrim(trim($validated['orderNumber']), '#');
        $contact = trim($validated['contact']);

        $order = Order::with('items')
            ->where('order_number', $orderNumber)
            ->where(function ($query) use ($contact) {
                $query->where('customer_email', $contact)
                    ->orWhere('customer_phone', $contact);
            })
            ->first();

        if (! $order) {
            return response()->json([
                'message' => "We couldn't find an order matching those details.",
            ], 404);
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
            // Nullable: a dashboard-created walk-in/POS sale has no shipping
            // address or necessarily a phone/email, unlike storefront checkout.
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'district' => ['nullable', 'string', 'max:255'],
            'paymentMethod' => ['required', 'in:cod,bkash,cash'],
            'bkashNumber' => ['nullable', 'string', 'max:32'],
            'shippingCost' => ['nullable', 'numeric', 'min:0'],
            'discountCode' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['nullable'],
            'items.*.name' => ['required', 'string'],
            'items.*.variant' => ['nullable', 'string'],
            'items.*.image' => ['nullable', 'string'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        // Dashboard-created (staff) sales skip phone verification entirely —
        // this is only fake-order protection for anonymous storefront
        // checkout. The route has no `auth:sanctum` middleware (guest
        // checkout must work), so staff-ness is resolved from an optional
        // bearer token instead — see CreateOrderDialog on the frontend,
        // which already sends one.
        $isStaffOrder = false;
        if ($bearerToken = $request->bearerToken()) {
            $user = PersonalAccessToken::findToken($bearerToken)?->tokenable;
            $isStaffOrder = $user?->canAccessDashboard() ?? false;
        }

        if (! $isStaffOrder) {
            $verifiedRecently = ! empty($validated['phone']) && PhoneVerification::where('phone', $validated['phone'])
                ->whereNotNull('verified_at')
                ->where('verified_at', '>', now()->subMinutes(30))
                ->exists();

            if (! $verifiedRecently) {
                throw ValidationException::withMessages([
                    'phone' => 'Please verify your phone number before placing the order.',
                ]);
            }
        }

        $order = DB::transaction(function () use ($validated) {
            $subtotal = collect($validated['items'])
                ->sum(fn ($item) => $item['price'] * $item['qty']);
            $shipping = (float) ($validated['shippingCost'] ?? 0);

            // Re-validated here rather than trusting whatever the
            // checkout page's earlier `/discounts/validate` preview said —
            // a code can expire, hit its usage limit, or get deactivated
            // between preview and submit.
            $discount = null;
            $discountAmount = 0.0;
            if (! empty($validated['discountCode'])) {
                $discount = Discount::findByCode($validated['discountCode'], lockForUpdate: true);

                if (! $discount) {
                    throw ValidationException::withMessages([
                        'discountCode' => "That discount code doesn't exist.",
                    ]);
                }

                $error = $discount->validationError($subtotal);
                if ($error) {
                    throw ValidationException::withMessages(['discountCode' => $error]);
                }

                if ($discount->isFreeShipping()) {
                    $shipping = 0.0;
                } else {
                    $discountAmount = $discount->amountOff($subtotal);
                }

                $discount->increment('used_count');
            }

            $order = Order::create([
                'order_number' => $this->nextOrderNumber(),
                'customer_name' => $validated['name'],
                'customer_email' => $validated['email'] ?? null,
                'customer_phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'district' => $validated['district'] ?? null,
                'subtotal' => $subtotal,
                'shipping_cost' => $shipping,
                'discount_code' => $discount?->code,
                'discount_amount' => $discountAmount,
                'total' => max(0, $subtotal + $shipping - $discountAmount),
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

        // Best-effort and after the transaction commits — a gateway hiccup
        // must never roll back or fail an otherwise-successful order.
        $this->sms->sendOrderConfirmation($order);

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

        $wasCancelled = $order->status === 'cancelled';
        $order->update(['status' => $validated['status']]);

        if ($validated['status'] === 'cancelled' && ! $wasCancelled) {
            $this->sms->sendOrderCancelled($order);
        }

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
            'payment' => match ($order->payment_method) {
                'bkash' => 'bKash',
                'cash' => 'Cash',
                default => 'COD',
            },
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
            'discountCode' => $order->discount_code,
            'discountAmount' => (float) $order->discount_amount,
            'bkashNumber' => $order->bkash_number,
            'courier' => $order->courier,
            'courierConsignmentId' => $order->courier_consignment_id,
            'courierTrackingCode' => $order->courier_tracking_code,
            'courierStatus' => $order->courier_status,
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
