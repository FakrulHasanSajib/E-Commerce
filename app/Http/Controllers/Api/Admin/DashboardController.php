<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(protected AnalyticsService $analyticsService) {}

    public function metrics(Request $request)
    {
        $period = $request->input('period', 'month');

        return response()->json([
            'data' => $this->analyticsService->getMetrics($period),
        ]);
    }

    public function salesChart(Request $request)
    {
        $period = $request->input('period', 'month');

        return response()->json([
            'data' => $this->analyticsService->getSalesChartData($period),
        ]);
    }

    public function topProducts(Request $request)
    {
        $limit = (int) $request->input('limit', 5);

        return response()->json([
            'data' => $this->analyticsService->getTopSellingProducts($limit),
        ]);
    }

    public function lowStock(Request $request)
    {
        $threshold = (int) $request->input('threshold', 5);
        $limit = (int) $request->input('limit', 10);

        return response()->json([
            'data' => $this->analyticsService->getLowStockItems($threshold, $limit),
        ]);
    }

    public function recentOrders(Request $request)
    {
        $limit = (int) $request->input('limit', 5);

        return response()->json([
            'data' => $this->analyticsService->getRecentOrders($limit),
        ]);
    }
}
