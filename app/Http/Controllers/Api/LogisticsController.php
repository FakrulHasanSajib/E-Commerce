<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PickupPointResource;
use App\Models\PickupPoint;
use App\Services\Logistics\LogisticsService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LogisticsController extends Controller
{
    public function __construct(protected LogisticsService $logisticsService) {}

    /**
     * Lists active pickup points for storefront checkout selection.
     */
    public function indexPickupPoints()
    {
        $points = PickupPoint::where('is_active', true)->get();

        return PickupPointResource::collection($points);
    }

    /**
     * Handles webhook callbacks from courier integrations.
     */
    public function webhook(Request $request, string $provider)
    {
        try {
            $this->logisticsService->processCourierWebhook($provider, $request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Courier webhook update processed successfully.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
