<?php

namespace App\Http\Controllers;

use App\Models\Discount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DiscountController extends Controller
{
    public function index(): JsonResponse
    {
        $discounts = Discount::orderByDesc('created_at')->get();

        return response()->json($discounts->map(fn (Discount $d) => $this->summarize($d))->values());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $discount = Discount::create($validated)->fresh();

        return response()->json($this->summarize($discount), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $discount = Discount::find($id);

        if (! $discount) {
            return response()->json(['message' => 'Discount not found'], 404);
        }

        $discount->update($this->validated($request, $discount->id));

        return response()->json($this->summarize($discount));
    }

    public function destroy(string $id): JsonResponse
    {
        $discount = Discount::find($id);

        if (! $discount) {
            return response()->json(['message' => 'Discount not found'], 404);
        }

        $discount->delete();

        return response()->json(null, 204);
    }

    /**
     * Public — checkout calls this to preview a code's effect before the
     * order is actually placed. `OrderController::store()` re-validates
     * independently rather than trusting whatever this returned.
     */
    public function validateCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'items' => ['array'],
            'items.*.price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.qty' => ['required_with:items', 'integer', 'min:1'],
        ]);

        $discount = Discount::findByCode($validated['code']);

        if (! $discount) {
            return response()->json(['message' => 'That discount code doesn\'t exist.'], 422);
        }

        $error = $discount->validationError((float) $validated['subtotal']);

        if ($error) {
            return response()->json(['message' => $error], 422);
        }

        if ($discount->isBogo()) {
            $items = $validated['items'] ?? [];
            $amountOff = $discount->bogoAmountOff($items);

            if ($amountOff <= 0) {
                return response()->json([
                    'message' => $this->bogoShortfallMessage($discount, $items),
                ], 422);
            }
        } else {
            $amountOff = $discount->amountOff((float) $validated['subtotal']);
        }

        return response()->json([
            'code' => $discount->code,
            'type' => $discount->type,
            'value' => $discount->value !== null ? (float) $discount->value : null,
            'buyQty' => $discount->buy_qty,
            'getQty' => $discount->get_qty,
            'amountOff' => $amountOff,
            'freeShipping' => $discount->isFreeShipping(),
        ]);
    }

    private function bogoShortfallMessage(Discount $discount, array $items): string
    {
        $groupSize = $discount->bogoGroupSize();
        $totalUnits = array_sum(array_map(fn ($item) => max(0, (int) ($item['qty'] ?? 0)), $items));
        $needed = max(1, $groupSize - $totalUnits);

        return "Buy {$discount->buy_qty}, get {$discount->get_qty} free — add {$needed} more item(s) to your cart to use this code.";
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('discounts', 'code')->ignore($ignoreId),
            ],
            'type' => ['required', 'in:percentage,fixed,free_shipping,bogo'],
            'value' => ['required_unless:type,free_shipping,bogo', 'nullable', 'numeric', 'min:0'],
            'buyQty' => ['required_if:type,bogo', 'nullable', 'integer', 'min:1'],
            'getQty' => ['required_if:type,bogo', 'nullable', 'integer', 'min:1'],
            'minSubtotal' => ['nullable', 'numeric', 'min:0'],
            'usageLimit' => ['nullable', 'integer', 'min:1'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date'],
            'active' => ['boolean'],
        ]);

        return [
            'code' => Str::upper(trim($validated['code'])),
            'type' => $validated['type'],
            'value' => in_array($validated['type'], ['free_shipping', 'bogo'], true) ? null : $validated['value'],
            'buy_qty' => $validated['type'] === 'bogo' ? $validated['buyQty'] : null,
            'get_qty' => $validated['type'] === 'bogo' ? $validated['getQty'] : null,
            'min_subtotal' => $validated['minSubtotal'] ?? null,
            'usage_limit' => $validated['usageLimit'] ?? null,
            'starts_at' => $validated['startsAt'] ?? null,
            'ends_at' => $validated['endsAt'] ?? null,
            'active' => $validated['active'] ?? true,
        ];
    }

    private function summarize(Discount $discount): array
    {
        return [
            'id' => (string) $discount->id,
            'code' => $discount->code,
            'type' => $discount->type,
            'value' => $discount->value !== null ? (float) $discount->value : null,
            'buyQty' => $discount->buy_qty,
            'getQty' => $discount->get_qty,
            'minSubtotal' => $discount->min_subtotal !== null ? (float) $discount->min_subtotal : null,
            'usageLimit' => $discount->usage_limit,
            'usedCount' => $discount->used_count,
            'startsAt' => $discount->starts_at?->toIso8601String(),
            'endsAt' => $discount->ends_at?->toIso8601String(),
            'active' => $discount->active,
        ];
    }
}
