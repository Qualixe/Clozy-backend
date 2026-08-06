<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SmsLog;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function __construct(private readonly SmsService $sms) {}

    /** Every SMS ever attempted (confirmation, cancellation, promotional), newest first. */
    public function logs(): JsonResponse
    {
        $logs = SmsLog::with('order')->orderByDesc('created_at')->limit(200)->get();

        return response()->json($logs->map(fn (SmsLog $l) => [
            'id' => (string) $l->id,
            'orderNumber' => $l->order ? '#'.$l->order->order_number : null,
            'type' => $l->type,
            'recipient' => $l->recipient,
            'message' => $l->message,
            'status' => $l->status,
            'response' => $l->response,
            'date' => $l->created_at->toIso8601String(),
        ])->values());
    }

    /**
     * Distinct phone numbers pulled from past orders — the pool the
     * dashboard's promotional-SMS composer picks recipients from.
     */
    public function recipients(): JsonResponse
    {
        $recipients = Order::query()
            ->whereNotNull('customer_phone')
            ->orderByDesc('created_at')
            ->get(['customer_name', 'customer_phone'])
            ->unique('customer_phone')
            ->map(fn (Order $o) => [
                'name' => $o->customer_name,
                'phone' => $o->customer_phone,
            ])
            ->values();

        return response()->json($recipients);
    }

    public function sendPromotional(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*' => ['string'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $logs = $this->sms->sendPromotional($validated['recipients'], $validated['message']);

        return response()->json([
            'sent' => collect($logs)->where('status', 'sent')->count(),
            'failed' => collect($logs)->where('status', 'failed')->count(),
        ]);
    }
}
