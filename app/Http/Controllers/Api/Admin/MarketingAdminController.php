<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\GiftCard;
use App\Models\Popup;
use App\Services\Marketing\GiftCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MarketingAdminController extends Controller
{
    public function __construct(protected GiftCardService $giftCardService) {}

    /**
     * List gift cards.
     */
    public function giftCards()
    {
        $giftCards = GiftCard::with('purchaser')->latest()->paginate(15);

        return response()->json([
            'data' => $giftCards->items(),
            'meta' => [
                'current_page' => $giftCards->currentPage(),
                'last_page' => $giftCards->lastPage(),
                'total' => $giftCards->total(),
            ],
        ]);
    }

    /**
     * Store a gift card.
     */
    public function storeGiftCard(Request $request)
    {
        $validated = $request->validate([
            'initial_value' => 'required|numeric|min:1',
            'expires_at' => 'nullable|date',
        ]);

        $giftCard = $this->giftCardService->createGiftCard(
            (float) $validated['initial_value'],
            $request->user()?->id,
            isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null
        );

        return response()->json([
            'message' => 'Gift card created successfully.',
            'data' => $giftCard,
        ], 201);
    }

    /**
     * List affiliates.
     */
    public function affiliates()
    {
        $affiliates = Affiliate::with('user')->latest()->paginate(15);

        return response()->json([
            'data' => $affiliates->items(),
            'meta' => [
                'current_page' => $affiliates->currentPage(),
                'last_page' => $affiliates->lastPage(),
                'total' => $affiliates->total(),
            ],
        ]);
    }

    /**
     * List popups.
     */
    public function popups()
    {
        $popups = Popup::latest()->get();

        return response()->json(['data' => $popups]);
    }

    /**
     * Store a popup configuration.
     */
    public function storePopup(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|string|in:exit_intent,newsletter,timed',
            'content' => 'required|array',
            'is_active' => 'nullable|boolean',
            'delay_seconds' => 'nullable|integer|min:0',
        ]);

        $popup = Popup::create([
            'title' => $validated['title'],
            'type' => $validated['type'],
            'content' => $validated['content'],
            'is_active' => $validated['is_active'] ?? true,
            'delay_seconds' => $validated['delay_seconds'] ?? 0,
        ]);

        return response()->json([
            'message' => 'Popup created successfully.',
            'data' => $popup,
        ], 201);
    }
}
