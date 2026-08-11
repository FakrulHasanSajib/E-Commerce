<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FlashSale\FlashSaleService;

class FlashSaleController extends Controller
{
    public function __construct(protected FlashSaleService $flashSaleService) {}

    /**
     * Get active flash sale deals.
     */
    public function index()
    {
        $deals = $this->flashSaleService->getActiveFlashSaleDeals();

        return response()->json([
            'data' => $deals,
        ], 200);
    }
}
