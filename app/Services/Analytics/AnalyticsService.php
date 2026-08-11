<?php

namespace App\Services\Analytics;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AnalyticsService
{
    /**
     * Get aggregate sales and customer signups metrics.
     */
    public function getMetrics(string $period = 'month'): array
    {
        return Cache::remember("dashboard:metrics:{$period}", now()->addMinutes(10), function () use ($period) {
            [$currentStart, $currentEnd, $prevStart, $prevEnd] = $this->getPeriodDates($period);

            // Current metrics
            $currentData = DB::table('orders')
                ->whereNotIn('status', ['cancelled', 'returned'])
                ->whereBetween('created_at', [$currentStart, $currentEnd])
                ->selectRaw('COUNT(id) as total_orders, SUM(grand_total) as total_revenue, AVG(grand_total) as aov')
                ->first();

            // Previous metrics
            $prevData = DB::table('orders')
                ->whereNotIn('status', ['cancelled', 'returned'])
                ->whereBetween('created_at', [$prevStart, $prevEnd])
                ->selectRaw('COUNT(id) as total_orders, SUM(grand_total) as total_revenue, AVG(grand_total) as aov')
                ->first();

            // Customer counts
            $currentCustomers = DB::table('users')->whereBetween('created_at', [$currentStart, $currentEnd])->count();
            $prevCustomers = DB::table('users')->whereBetween('created_at', [$prevStart, $prevEnd])->count();
            $totalCustomers = DB::table('users')->count();

            $currentRevenue = (float) ($currentData->total_revenue ?? 0.00);
            $prevRevenue = (float) ($prevData->total_revenue ?? 0.00);

            $currentOrders = (int) ($currentData->total_orders ?? 0);
            $prevOrders = (int) ($prevData->total_orders ?? 0);

            $currentAOV = (float) ($currentData->aov ?? 0.00);
            $prevAOV = (float) ($prevData->aov ?? 0.00);

            return [
                'revenue' => [
                    'current' => $currentRevenue,
                    'previous' => $prevRevenue,
                    'growth_rate' => $this->calculateGrowth($currentRevenue, $prevRevenue),
                ],
                'orders' => [
                    'current' => $currentOrders,
                    'previous' => $prevOrders,
                    'growth_rate' => $this->calculateGrowth($currentOrders, $prevOrders),
                ],
                'aov' => [
                    'current' => $currentAOV,
                    'previous' => $prevAOV,
                    'growth_rate' => $this->calculateGrowth($currentAOV, $prevAOV),
                ],
                'customers' => [
                    'current' => $currentCustomers,
                    'previous' => $prevCustomers,
                    'total' => $totalCustomers,
                    'growth_rate' => $this->calculateGrowth($currentCustomers, $prevCustomers),
                ],
            ];
        });
    }

    /**
     * Get aggregated daily or monthly sales chart points.
     */
    public function getSalesChartData(string $period = 'month'): array
    {
        return Cache::remember("dashboard:sales_chart:{$period}", now()->addMinutes(10), function () use ($period) {
            [$currentStart, $currentEnd] = $this->getPeriodDates($period);

            $orders = DB::table('orders')
                ->whereNotIn('status', ['cancelled', 'returned'])
                ->whereBetween('created_at', [$currentStart, $currentEnd])
                ->select(['created_at', 'grand_total'])
                ->oldest('created_at')
                ->get();

            // Group in PHP memory to bypass database function-wrapped GROUP BY indexes limits
            $grouped = $orders->groupBy(function ($order) use ($period) {
                $date = Carbon::parse($order->created_at);
                if ($period === 'day') {
                    return $date->format('H:00');
                }
                if ($period === 'year') {
                    return $date->format('Y-m');
                }

                return $date->format('Y-m-d');
            });

            $chartData = [];
            foreach ($grouped as $key => $items) {
                $chartData[] = [
                    'label' => (string) $key,
                    'revenue' => (float) $items->sum('grand_total'),
                    'orders_count' => (int) $items->count(),
                ];
            }

            return $chartData;
        });
    }

    /**
     * Get top-selling products by quantity.
     */
    public function getTopSellingProducts(int $limit = 5): Collection
    {
        return Cache::remember("dashboard:top_products:{$limit}", now()->addMinutes(10), function () use ($limit) {
            $items = OrderItem::query()
                ->selectRaw('product_id, SUM(quantity) as total_qty, SUM(price * quantity) as total_sales')
                ->whereHas('order', function ($q) {
                    $q->whereNotIn('status', ['cancelled', 'returned']);
                })
                ->groupBy('product_id')
                ->orderByDesc('total_qty')
                ->limit($limit)
                ->get();

            // Prevent N+1 eager loads
            $items->load(['product.media']);

            return $items->map(function ($item) {
                $product = $item->product;
                $thumbUrl = null;
                if ($product) {
                    $thumb = $product->media->where('pivot.collection_name', 'thumbnail')->first();
                    if ($thumb) {
                        $thumbUrl = Storage::disk($thumb->disk)->url($thumb->path.'/'.$thumb->filename);
                    }
                }

                return [
                    'product_id' => $item->product_id,
                    'product_name' => $product ? $product->name : 'Deleted Product',
                    'thumbnail_url' => $thumbUrl,
                    'total_quantity' => (int) $item->total_qty,
                    'total_revenue' => (float) $item->total_sales,
                ];
            });
        });
    }

    /**
     * Get simple and variable items running low on stock.
     */
    public function getLowStockItems(int $threshold = 5, int $limit = 10): Collection
    {
        return Cache::remember("dashboard:low_stock:{$threshold}", now()->addMinutes(10), function () use ($threshold, $limit) {
            $simpleProducts = Product::query()
                ->where('type', 'simple')
                ->where('manage_stock', true)
                ->where('stock_quantity', '<=', $threshold)
                ->limit($limit)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'type' => 'simple',
                    'name' => $p->name,
                    'stock_quantity' => $p->stock_quantity,
                ]);

            $variants = ProductVariant::query()
                ->with(['product', 'attributeValues'])
                ->where('manage_stock', true)
                ->where('stock_quantity', '<=', $threshold)
                ->limit($limit)
                ->get()
                ->map(function ($v) {
                    $optionsString = $v->attributeValues->pluck('value')->implode(', ');
                    $variantName = $optionsString ?: $v->sku;

                    return [
                        'id' => $v->id,
                        'type' => 'variant',
                        'name' => ($v->product ? $v->product->name : 'Product').' ('.$variantName.')',
                        'stock_quantity' => $v->stock_quantity,
                    ];
                });

            return $simpleProducts->concat($variants)
                ->sortBy('stock_quantity')
                ->take($limit)
                ->values();
        });
    }

    /**
     * Get recent orders listing.
     */
    public function getRecentOrders(int $limit = 5): Collection
    {
        return Cache::remember("dashboard:recent_orders:{$limit}", now()->addMinutes(10), function () use ($limit) {
            return Order::query()
                ->with(['user'])
                ->latest()
                ->limit($limit)
                ->get()
                ->map(fn ($order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name ?: ($order->user ? $order->user->name : 'Guest'),
                    'grand_total' => (float) $order->grand_total,
                    'status' => $order->status,
                    'created_at' => $order->created_at->toIso8601String(),
                ]);
        });
    }

    /**
     * Helper to resolve start and end range Carbon dates.
     */
    protected function getPeriodDates(string $period): array
    {
        $now = Carbon::now();

        switch ($period) {
            case 'day':
                return [
                    $now->copy()->startOfDay(),
                    $now->copy()->endOfDay(),
                    $now->copy()->subDay()->startOfDay(),
                    $now->copy()->subDay()->endOfDay(),
                ];
            case 'week':
                return [
                    $now->copy()->subDays(6)->startOfDay(),
                    $now->copy()->endOfDay(),
                    $now->copy()->subDays(13)->startOfDay(),
                    $now->copy()->subDays(7)->endOfDay(),
                ];
            case 'year':
                return [
                    $now->copy()->subDays(364)->startOfDay(),
                    $now->copy()->endOfDay(),
                    $now->copy()->subDays(729)->startOfDay(),
                    $now->copy()->subDays(365)->endOfDay(),
                ];
            case 'month':
            default:
                return [
                    $now->copy()->subDays(29)->startOfDay(),
                    $now->copy()->endOfDay(),
                    $now->copy()->subDays(59)->startOfDay(),
                    $now->copy()->subDays(30)->endOfDay(),
                ];
        }
    }

    /**
     * Compute growth rate percentage.
     */
    protected function calculateGrowth(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.00 : 0.00;
        }

        return (float) number_format((($current - $previous) / $previous) * 100, 2);
    }
}
