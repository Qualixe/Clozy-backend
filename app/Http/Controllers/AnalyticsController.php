<?php

namespace App\Http\Controllers;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

        $stats = $this->buildStats(Carbon::now()->subDays(90));

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

    /**
     * Aggregated entirely in the database rather than pulling every order
     * (and every order's items) into PHP and summing them in a loop — the
     * previous approach loaded the full 90-day order+item history into
     * memory on every AI-insights request. Each figure below preserves the
     * exact original PHP-loop semantics (see git history), just computed
     * as a SUM/COUNT/GROUP BY instead.
     */
    private function buildStats(Carbon $since): array
    {
        // Orders are only ever created with a lowercase status (see the
        // `status` enum on the orders table / updateStatus()'s validation),
        // but the original loop normalized with strtolower() before
        // counting — LOWER() here matches that same defensive behavior.
        $statusCounts = ['fulfilled' => 0, 'processing' => 0, 'cancelled' => 0];

        $statusRows = Order::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('LOWER(status) as status, COUNT(*) as cnt')
            ->groupByRaw('LOWER(status)')
            ->pluck('cnt', 'status');

        foreach ($statusRows as $status => $count) {
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + (int) $count;
        }

        // Total orders in the period across every status — summing the
        // per-status counts already queried above avoids a second COUNT
        // query for what's arithmetically the same number.
        $totalOrders = (int) $statusRows->sum();

        $revenueRow = Order::query()
            ->where('created_at', '>=', $since)
            ->whereRaw('LOWER(status) != ?', ['cancelled'])
            ->selectRaw('COALESCE(SUM(total), 0) as revenue, COUNT(*) as cnt')
            ->first();

        $totalRevenue = (float) $revenueRow->revenue;
        $nonCancelledCount = (int) $revenueRow->cnt;

        $revenueByDay = Order::query()
            ->where('created_at', '>=', $since)
            ->whereRaw('LOWER(status) != ?', ['cancelled'])
            ->selectRaw('DATE(created_at) as day, SUM(total) as revenue')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('day')
            ->pluck('revenue', 'day')
            ->map(fn ($revenue) => (float) $revenue)
            ->all();

        $topProductsByRevenue = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', $since)
            ->whereRaw('LOWER(orders.status) != ?', ['cancelled'])
            ->selectRaw('order_items.name as name, SUM(order_items.price * order_items.qty) as revenue')
            ->groupBy('order_items.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'revenue' => round((float) $row->revenue, 2)])
            ->values()
            ->all();

        return [
            'periodDays' => 90,
            'totalOrders' => $totalOrders,
            'totalRevenue' => round($totalRevenue, 2),
            'avgOrderValue' => $nonCancelledCount > 0 ? round($totalRevenue / $nonCancelledCount, 2) : 0,
            'ordersByStatus' => $statusCounts,
            'revenueByDay' => $revenueByDay,
            'topProductsByRevenue' => $topProductsByRevenue,
        ];
    }
}
