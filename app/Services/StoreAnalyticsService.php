<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregated order stats, entirely in the database rather than pulling
 * every order (and every order's items) into PHP and summing in a loop.
 * Shared by AnalyticsController::aiInsights() and AdminChatController's
 * get_analytics_summary tool, so the two never drift on how a figure is
 * computed.
 */
class StoreAnalyticsService
{
    public function summary(int $periodDays = 90): array
    {
        $since = Carbon::now()->subDays($periodDays);

        // Orders are only ever created with a lowercase status (see the
        // `status` enum on the orders table / updateStatus()'s validation),
        // but LOWER() here is a defensive match regardless.
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
            'periodDays' => $periodDays,
            'totalOrders' => $totalOrders,
            'totalRevenue' => round($totalRevenue, 2),
            'avgOrderValue' => $nonCancelledCount > 0 ? round($totalRevenue / $nonCancelledCount, 2) : 0,
            'ordersByStatus' => $statusCounts,
            'revenueByDay' => $revenueByDay,
            'topProductsByRevenue' => $topProductsByRevenue,
        ];
    }
}
