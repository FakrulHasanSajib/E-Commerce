<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TaxClassResource;
use App\Models\TaxClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TaxClassController extends Controller
{
    /**
     * Display a listing of tax classes.
     */
    public function index()
    {
        Gate::authorize('manage-products');

        $classes = TaxClass::with('rate')->paginate(20);

        return TaxClassResource::collection($classes);
    }

    /**
     * Store a newly created tax class in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize('manage-products');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:tax_classes,slug',
            'rate' => 'nullable|array',
            'rate.name' => 'required_with:rate|string|max:100',
            'rate.rate' => 'required_with:rate|numeric|min:0|max:100',
            'rate.is_active' => 'nullable|boolean',
        ]);

        $taxClass = DB::transaction(function () use ($validated) {
            $taxClass = TaxClass::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
            ]);

            if (! empty($validated['rate'])) {
                $taxClass->rate()->create([
                    'name' => $validated['rate']['name'],
                    'rate' => $validated['rate']['cost'] ?? $validated['rate']['rate'],
                    'is_active' => $validated['rate']['is_active'] ?? true,
                ]);
            }

            return $taxClass;
        });

        Cache::forget('active_tax_rates_by_class');

        $taxClass->load('rate');

        return new TaxClassResource($taxClass);
    }

    /**
     * Display the specified tax class.
     */
    public function show(TaxClass $taxClass)
    {
        Gate::authorize('manage-products');

        $taxClass->load('rate');

        return new TaxClassResource($taxClass);
    }

    /**
     * Update the specified tax class in storage.
     */
    public function update(Request $request, TaxClass $taxClass)
    {
        Gate::authorize('manage-products');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:tax_classes,slug,'.$taxClass->id,
            'rate' => 'nullable|array',
            'rate.name' => 'required_with:rate|string|max:100',
            'rate.rate' => 'required_with:rate|numeric|min:0|max:100',
            'rate.is_active' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($validated, $taxClass) {
            $taxClass->update([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
            ]);

            $taxClass->rate()->delete();

            if (! empty($validated['rate'])) {
                $taxClass->rate()->create([
                    'name' => $validated['rate']['name'],
                    'rate' => $validated['rate']['rate'],
                    'is_active' => $validated['rate']['is_active'] ?? true,
                ]);
            }
        });

        Cache::forget('active_tax_rates_by_class');

        $taxClass->load('rate');

        return new TaxClassResource($taxClass);
    }

    /**
     * Remove the specified tax class from storage.
     */
    public function destroy(TaxClass $taxClass)
    {
        Gate::authorize('manage-products');

        $taxClass->delete();

        Cache::forget('active_tax_rates_by_class');

        return response()->noContent();
    }
}
