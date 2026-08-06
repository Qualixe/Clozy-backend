<?php

namespace App\Http\Controllers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Throwable;

class AnalyticsController extends Controller
{
    /**
     * On-demand AI-written summary of recent store performance. Never
     * throws on a missing/invalid key or a Claude API failure — always
     * returns 200 with either an insight or a reason it couldn't be
     * generated, so the dashboard can render a friendly message instead of
     * a broken page.
     */
    public function aiInsights(): JsonResponse
    {
        $settings = StoreSetting::current();

        if (! $settings->anthropic_api_key) {
            return response()->json([
                'configured' => false,
                'insight' => null,
                'error' => null,
            ]);
        }

        $orders = Order::with('items')
            ->where('created_at', '>=', Carbon::now()->subDays(90))
            ->get();

        $stats = $this->buildStats($orders);

        try {
            $client = new Client(apiKey: $settings->anthropic_api_key);

            $message = $client->messages->create(
                model: 'claude-opus-5',
                maxTokens: 1024,
                outputConfig: ['effort' => 'medium'],
                system: 'You are an e-commerce analytics assistant for Clozy, an online fashion and footwear store. '
                    .'You will be given aggregated order data as JSON, covering the last 90 days. Write a concise, '
                    .'plain-language summary for the store owner: 2-4 short paragraphs or bullet points covering '
                    .'overall performance, the most notable trend(s), and 1-3 concrete, specific suggestions. '
                    .'Only use numbers present in the data — never invent figures. Amounts are in BDT (Bangladeshi '
                    .'Taka). Do not use markdown headings; plain sentences and simple "- " bullets only. '
                    .'Keep the whole response under 200 words.',
                messages: [
                    ['role' => 'user', 'content' => json_encode($stats, JSON_PRETTY_PRINT)],
                ],
            );

            $text = '';
            foreach ($message->content as $block) {
                if ($block->type === 'text') {
                    $text .= $block->text;
                }
            }

            if ($message->stopReason === 'refusal' || $text === '') {
                return response()->json([
                    'configured' => true,
                    'insight' => null,
                    'error' => 'Claude declined to generate a summary for this data.',
                ]);
            }

            return response()->json([
                'configured' => true,
                'insight' => $text,
                'error' => null,
            ]);
        } catch (APIStatusException $e) {
            return response()->json([
                'configured' => true,
                'insight' => null,
                'error' => 'Claude API error: '.$e->getMessage(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'configured' => true,
                'insight' => null,
                'error' => 'Could not reach the Claude API: '.$e->getMessage(),
            ]);
        }
    }

    private function buildStats(\Illuminate\Support\Collection $orders): array
    {
        $totalRevenue = 0.0;
        $statusCounts = ['fulfilled' => 0, 'processing' => 0, 'cancelled' => 0];
        $revenueByDay = [];
        $productRevenue = [];

        foreach ($orders as $order) {
            $status = strtolower($order->status);
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

            if ($status === 'cancelled') {
                continue;
            }

            $total = (float) $order->total;
            $totalRevenue += $total;

            $day = $order->created_at->toDateString();
            $revenueByDay[$day] = ($revenueByDay[$day] ?? 0) + $total;

            foreach ($order->items as $item) {
                $productRevenue[$item->name] = ($productRevenue[$item->name] ?? 0)
                    + ((float) $item->price * $item->qty);
            }
        }

        arsort($productRevenue);
        ksort($revenueByDay);

        $nonCancelledCount = $orders->count() - $statusCounts['cancelled'];

        return [
            'periodDays' => 90,
            'totalOrders' => $orders->count(),
            'totalRevenue' => round($totalRevenue, 2),
            'avgOrderValue' => $nonCancelledCount > 0 ? round($totalRevenue / $nonCancelledCount, 2) : 0,
            'ordersByStatus' => $statusCounts,
            'revenueByDay' => $revenueByDay,
            'topProductsByRevenue' => array_slice(
                array_map(
                    fn ($name, $revenue) => ['name' => $name, 'revenue' => round($revenue, 2)],
                    array_keys($productRevenue),
                    array_values($productRevenue),
                ),
                0,
                5,
            ),
        ];
    }
}
