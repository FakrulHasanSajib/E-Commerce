<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ShippingZoneResource;
use App\Models\ShippingZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ShippingZoneController extends Controller
{
    /**
     * Display a listing of shipping zones.
     */
    public function index()
    {
        Gate::authorize('manage-products');

        $zones = ShippingZone::with('locations', 'methods.weightRates')->paginate(20);

        return ShippingZoneResource::collection($zones);
    }

    /**
     * Store a newly created shipping zone in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize('manage-products');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'is_active' => 'nullable|boolean',
            'locations' => 'nullable|array',
            'locations.*.country_code' => 'required|string|size:2',
            'locations.*.division' => 'nullable|string|max:100',
            'locations.*.district' => 'nullable|string|max:100',
            'locations.*.upazila' => 'nullable|string|max:100',
            'methods' => 'nullable|array',
            'methods.*.name' => 'required|string|max:100',
            'methods.*.type' => 'required|string|in:flat_rate,weight_based,free_shipping',
            'methods.*.cost' => 'required|numeric|min:0',
            'methods.*.min_order_amount' => 'nullable|numeric|min:0',
            'methods.*.is_active' => 'nullable|boolean',
            'methods.*.weight_rates' => 'nullable|array',
            'methods.*.weight_rates.*.max_weight' => 'required|numeric|min:0',
            'methods.*.weight_rates.*.cost' => 'required|numeric|min:0',
        ]);

        $zone = DB::transaction(function () use ($validated) {
            $zone = ShippingZone::create([
                'name' => $validated['name'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (! empty($validated['locations'])) {
                foreach ($validated['locations'] as $locData) {
                    $zone->locations()->create($locData);
                }
            }

            if (! empty($validated['methods'])) {
                foreach ($validated['methods'] as $methodData) {
                    $method = $zone->methods()->create([
                        'name' => $methodData['name'],
                        'type' => $methodData['type'],
                        'cost' => $methodData['cost'],
                        'min_order_amount' => $methodData['min_order_amount'] ?? 0.00,
                        'is_active' => $methodData['is_active'] ?? true,
                    ]);

                    if ($methodData['type'] === 'weight_based' && ! empty($methodData['weight_rates'])) {
                        foreach ($methodData['weight_rates'] as $rateData) {
                            $method->weightRates()->create($rateData);
                        }
                    }
                }
            }

            return $zone;
        });

        ShippingZone::clearCache();

        $zone->load('locations', 'methods.weightRates');

        return new ShippingZoneResource($zone);
    }

    /**
     * Display the specified shipping zone.
     */
    public function show(ShippingZone $shippingZone)
    {
        Gate::authorize('manage-products');

        $shippingZone->load('locations', 'methods.weightRates');

        return new ShippingZoneResource($shippingZone);
    }

    /**
     * Update the specified shipping zone in storage.
     */
    public function update(Request $request, ShippingZone $shippingZone)
    {
        Gate::authorize('manage-products');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'is_active' => 'nullable|boolean',
            'locations' => 'nullable|array',
            'locations.*.country_code' => 'required|string|size:2',
            'locations.*.division' => 'nullable|string|max:100',
            'locations.*.district' => 'nullable|string|max:100',
            'locations.*.upazila' => 'nullable|string|max:100',
            'methods' => 'nullable|array',
            'methods.*.name' => 'required|string|max:100',
            'methods.*.type' => 'required|string|in:flat_rate,weight_based,free_shipping',
            'methods.*.cost' => 'required|numeric|min:0',
            'methods.*.min_order_amount' => 'nullable|numeric|min:0',
            'methods.*.is_active' => 'nullable|boolean',
            'methods.*.weight_rates' => 'nullable|array',
            'methods.*.weight_rates.*.max_weight' => 'required|numeric|min:0',
            'methods.*.weight_rates.*.cost' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $shippingZone) {
            $shippingZone->update([
                'name' => $validated['name'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Sync Locations: simple delete and recreate
            $shippingZone->locations()->delete();
            if (! empty($validated['locations'])) {
                foreach ($validated['locations'] as $locData) {
                    $shippingZone->locations()->create($locData);
                }
            }

            // Sync Methods: delete and recreate
            $shippingZone->methods()->delete();
            if (! empty($validated['methods'])) {
                foreach ($validated['methods'] as $methodData) {
                    $method = $shippingZone->methods()->create([
                        'name' => $methodData['name'],
                        'type' => $methodData['type'],
                        'cost' => $methodData['cost'],
                        'min_order_amount' => $methodData['min_order_amount'] ?? 0.00,
                        'is_active' => $methodData['is_active'] ?? true,
                    ]);

                    if ($methodData['type'] === 'weight_based' && ! empty($methodData['weight_rates'])) {
                        foreach ($methodData['weight_rates'] as $rateData) {
                            $method->weightRates()->create($rateData);
                        }
                    }
                }
            }
        });

        ShippingZone::clearCache();

        $shippingZone->load('locations', 'methods.weightRates');

        return new ShippingZoneResource($shippingZone);
    }

    /**
     * Remove the specified shipping zone from storage.
     */
    public function destroy(ShippingZone $shippingZone)
    {
        Gate::authorize('manage-products');

        $shippingZone->delete();

        ShippingZone::clearCache();

        return response()->noContent();
    }
}
