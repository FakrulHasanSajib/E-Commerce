<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FlashSaleAdminController extends Controller
{
    /**
     * Display a listing of all flash sales (active, scheduled, expired).
     */
    public function index(Request $request)
    {
        $sales = FlashSale::with(['items.product'])
            ->latest()
            ->paginate($request->input('per_page', 20));

        return response()->json($sales);
    }

    /**
     * Store a newly created flash sale campaign.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'is_active' => 'nullable|boolean',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.discount_price' => 'required|numeric|min:0',
            'items.*.quantity_limit' => 'required|integer|min:1',
        ]);

        $flashSale = DB::transaction(function () use ($validated) {
            $sale = FlashSale::create([
                'title' => $validated['title'],
                'start_at' => $validated['start_at'],
                'end_at' => $validated['end_at'],
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (!empty($validated['items'])) {
                foreach ($validated['items'] as $item) {
                    $sale->items()->create([
                        'product_id' => $item['product_id'],
                        'discount_price' => $item['discount_price'],
                        'quantity_limit' => $item['quantity_limit'],
                        'sold_quantity' => 0,
                    ]);
                }
            }

            return $sale;
        });

        $flashSale->load('items.product');

        return response()->json([
            'message' => 'Flash sale campaign created successfully.',
            'data' => $flashSale,
        ], 201);
    }

    /**
     * Display the specified flash sale campaign.
     */
    public function show($id)
    {
        $flashSale = FlashSale::with('items.product')->findOrFail($id);

        return response()->json([
            'data' => $flashSale,
        ]);
    }

    /**
     * Update the specified flash sale campaign in storage.
     */
    public function update(Request $request, $id)
    {
        $flashSale = FlashSale::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'start_at' => 'sometimes|required|date',
            'end_at' => 'sometimes|required|date|after:start_at',
            'is_active' => 'nullable|boolean',
            'items' => 'nullable|array',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.discount_price' => 'required|numeric|min:0',
            'items.*.quantity_limit' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($flashSale, $validated) {
            $flashSale->update([
                'title' => $validated['title'] ?? $flashSale->title,
                'start_at' => $validated['start_at'] ?? $flashSale->start_at,
                'end_at' => $validated['end_at'] ?? $flashSale->end_at,
                'is_active' => isset($validated['is_active']) ? $validated['is_active'] : $flashSale->is_active,
            ]);

            if (isset($validated['items'])) {
                $flashSale->items()->delete();
                foreach ($validated['items'] as $item) {
                    $flashSale->items()->create([
                        'product_id' => $item['product_id'],
                        'discount_price' => $item['discount_price'],
                        'quantity_limit' => $item['quantity_limit'],
                        'sold_quantity' => 0,
                    ]);
                }
            }
        });

        $flashSale->load('items.product');

        return response()->json([
            'message' => 'Flash sale campaign updated successfully.',
            'data' => $flashSale,
        ]);
    }

    /**
     * Remove the specified flash sale campaign from storage.
     */
    public function destroy($id)
    {
        $flashSale = FlashSale::findOrFail($id);

        DB::transaction(function () use ($flashSale) {
            $flashSale->items()->delete();
            $flashSale->delete();
        });

        return response()->json([
            'message' => 'Flash sale campaign deleted successfully.',
        ]);
    }
}
