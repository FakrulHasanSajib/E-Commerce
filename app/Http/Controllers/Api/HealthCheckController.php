<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ops\HealthCheckService;

class HealthCheckController extends Controller
{
    public function __construct(protected HealthCheckService $healthCheckService) {}

    /**
     * System Health Check API Endpoint (/health).
     */
    public function index()
    {
        $health = $this->healthCheckService->checkHealth();

        $statusCode = $health['is_healthy'] ? 200 : 503;

        return response()->json($health, $statusCode);
    }
}
