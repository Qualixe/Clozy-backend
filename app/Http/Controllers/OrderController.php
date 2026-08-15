<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use App\Models\Order;
use App\Models\PhoneVerification;
use App\Models\Product;
use App\Models\StoreSetting;
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
            'paymentMethod' => ['required', 'in:cod,bkash,cash,bkash_shipping_advance,bkash_partial_advance'],
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
        $user = null;
        if ($bearerToken = $request->bearerToken()) {
            $user = PersonalAccessToken::findToken($bearerToken)?->tokenable;

            if ($user?->canAccessDashboard()) {
                if (! $user->can('create_orders')) {
                    return response()->json([
                        'message' => "You don't have permission to create orders.",
                    ], 403);
                }

                $isStaffOrder = true;
            }
        }

        if (! $isStaffOrder) {
            // A logged-in customer whose account phone is already verified
            // (see AuthController::confirmPhone) doesn't need to redo the
            // OTP flow, as long as the checkout number matches — otherwise
            // fall back to the ephemeral PhoneVerification record.
            $accountPhoneVerified = $user
                && ! empty($validated['phone'])
                && $user->phone === $validated['phone']
                && $user->phone_verified_at !== null;

            $verifiedRecently = $accountPhoneVerified || (! empty($validated['phone']) && PhoneVerification::where('phone', $validated['phone'])
                ->whereNotNull('verified_at')
                ->where('verified_at', '>', now()->subMinutes(30))
                ->exists());

            if (! $verifiedRecently) {
                throw ValidationException::withMessages([
                    'phone' => 'Please verify your phone number before placing the order.',
                ]);
            }

            // Storefront checkout must never trust a client-supplied price —
            // otherwise `POST /orders` with a hand-edited price is free
            // money. Re-derive every line item from the product/variant
            // record instead. Staff/POS sales are exempt: they legitimately
            // set custom prices and may have no catalog productId at all.
            //
            // Fetched as a single batch (rather than one query per line
            // item inside resolveVerifiedPrice()) to avoid an N+1 on carts
            // with several line items.
            $productIds = collect($validated['items'])
                ->pluck('productId')
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $products = Product::with('variants.optionValues.option')
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            $validated['items'] = array_map(
                fn (array $item) => [...$item, 'price' => $this->resolveVerifiedPrice($item, $products)],
                $validated['items']
            );
        }

        $order = DB::transaction(function () use ($validated, $isStaffOrder) {
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
                } elseif ($discount->isBogo()) {
                    $discountAmount = $discount->bogoAmountOff($validated['items']);
                    if ($discountAmount <= 0) {
                        $groupSize = $discount->bogoGroupSize();
                        $totalUnits = collect($validated['items'])->sum(fn ($item) => (int) $item['qty']);
                        $needed = max(1, $groupSize - $totalUnits);

                        throw ValidationException::withMessages([
                            'discountCode' => "Buy {$discount->buy_qty}, get {$discount->get_qty} free — add {$needed} more item(s) to your cart to use this code.",
                        ]);
                    }
                } else {
                    $discountAmount = $discount->amountOff($subtotal);
                }

                $discount->increment('used_count');
            }

            $total = max(0, $subtotal + $shipping - $discountAmount);

            // The two hybrid methods charge part of the order via bKash now
            // and leave the rest for the courier to collect on delivery —
            // see Order::codAmountDue()/onlineAmountDue(). Computed here
            // (not trusted from the client) so the amount actually charged
            // matches what the store owner configured.
            $advanceAmount = null;
            if ($validated['paymentMethod'] === 'bkash_shipping_advance') {
                $settings = StoreSetting::current();
                if (! $settings->bkash_shipping_advance_enabled) {
                    throw ValidationException::withMessages([
                        'paymentMethod' => 'This payment method is not available.',
                    ]);
                }
                $advanceAmount = round($shipping, 2);
            } elseif ($validated['paymentMethod'] === 'bkash_partial_advance') {
                $settings = StoreSetting::current();
                if (! $settings->bkash_partial_advance_enabled) {
                    throw ValidationException::withMessages([
                        'paymentMethod' => 'This payment method is not available.',
                    ]);
                }
                // The advance is the full shipping fee plus a portion of the
                // product price — not a percentage of the whole order — so
                // shipping is always covered upfront and the rest is COD.
                $productPortion = $settings->bkash_partial_advance_mode === 'fixed'
                    ? (float) $settings->bkash_partial_advance_fixed_amount
                    : round($subtotal * ((float) $settings->bkash_partial_advance_percent / 100), 2);
                $advanceAmount = min(round($shipping, 2) + $productPortion, $total);
            }

            // Nothing to charge online — e.g. shipping-fee-advance on a free
            // shipping order — so there's no bKash step to wait on.
            $requiresOnlinePayment = match ($validated['paymentMethod']) {
                'bkash' => true,
                'bkash_shipping_advance', 'bkash_partial_advance' => $advanceAmount > 0,
                default => false,
            };

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
                'total' => $total,
                'advance_amount' => $advanceAmount,
                'payment_method' => $validated['paymentMethod'],
                // Storefront bKash-family orders are settled through the
                // real gateway (see BkashController) — pending until the
                // customer completes payment there. Staff-created/COD/cash
                // orders (and hybrid orders with nothing due online) have
                // no such step, so payment_status stays null.
                'payment_status' => (! $isStaffOrder && $requiresOnlinePayment) ? 'pending' : null,
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
        // Storefront bKash orders aren't confirmed yet at this point — that
        // SMS fires from BkashController::callback() once payment actually
        // succeeds, to avoid confirming an order nobody has paid for.
        if ($order->payment_status !== 'pending') {
            $this->sms->sendOrderConfirmation($order);
        }

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

    /**
     * Looks up the real, current price for a cart line item from the
     * catalog rather than trusting `item.price` from the request. Matches
     * the exact "Color / Size" label the storefront builds when adding to
     * cart (see single-product.tsx) against each variant's option values.
     *
     * `$products` is the whole cart's products (with variants) fetched
     * up front in a single batched query by the caller — see store() —
     * rather than this method querying per line item.
     */
    private function resolveVerifiedPrice(array $item, \Illuminate\Support\Collection $products): float
    {
        $productId = is_numeric($item['productId'] ?? null) ? (int) $item['productId'] : null;

        if ($productId === null) {
            throw ValidationException::withMessages([
                'items' => 'One or more items in your cart are invalid. Please refresh and try again.',
            ]);
        }

        $product = $products->get($productId);

        if (! $product) {
            throw ValidationException::withMessages([
                'items' => 'One or more items in your cart are no longer available.',
            ]);
        }

        if ($product->variants->isEmpty()) {
            return (float) $product->price;
        }

        $variantLabel = trim((string) ($item['variant'] ?? ''));
        $variant = $product->variants->first(function ($variant) use ($variantLabel) {
            $color = $variant->optionValues->firstWhere('option.name', 'Color')?->value;
            $size = $variant->optionValues->firstWhere('option.name', 'Size')?->value;
            $label = collect([$color, $size])->filter()->implode(' / ');

            return $label === $variantLabel;
        });

        if (! $variant) {
            throw ValidationException::withMessages([
                'items' => 'The selected option for one of your items is no longer available.',
            ]);
        }

        return (float) ($variant->price ?? $product->price);
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
                'bkash_shipping_advance' => 'bKash (Shipping Advance)',
                'bkash_partial_advance' => 'bKash (Advance Payment)',
                default => 'COD',
            },
            'paymentStatus' => $order->payment_status,
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
            'advanceAmount' => $order->advance_amount !== null ? (float) $order->advance_amount : null,
            'codAmountDue' => $order->codAmountDue(),
            'bkashNumber' => $order->bkash_number,
            'paymentStatus' => $order->payment_status,
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
