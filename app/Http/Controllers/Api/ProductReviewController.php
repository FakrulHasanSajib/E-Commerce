<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductReviewResource;
use App\Models\ProductReview;
use App\Services\Review\ReviewService;
use Illuminate\Http\Request;

class ProductReviewController extends Controller
{
    public function __construct(protected ReviewService $reviewService) {}

    /**
     * Get paginated approved reviews for a product.
     */
    public function index($productId)
    {
        $reviews = ProductReview::where('product_id', $productId)
            ->where('status', 'approved')
            ->with(['user', 'media'])
            ->latest()
            ->paginate(15);

        return ProductReviewResource::collection($reviews);
    }

    /**
     * Submit a review for a product.
     */
    public function store(Request $request, $productId)
    {
        $user = $request->user('sanctum');

        $validated = $request->validate([
            'rating' => 'required|integer|between:1,5',
            'title' => 'nullable|string|max:255',
            'comment' => 'nullable|string',
            'media_ids' => 'nullable|array',
            'media_ids.*' => 'integer|exists:media,id',
        ]);

        $mediaIds = $validated['media_ids'] ?? [];

        $review = $this->reviewService->submitReview(
            $user,
            (int) $productId,
            $validated['rating'],
            $validated['title'] ?? null,
            $validated['comment'] ?? null,
            $mediaIds
        );

        return new ProductReviewResource($review->load(['user', 'media']));
    }

    /**
     * Update an existing review.
     */
    public function update(Request $request, $productId, $reviewId)
    {
        $user = $request->user('sanctum');

        $review = ProductReview::where('product_id', $productId)->findOrFail($reviewId);

        $validated = $request->validate([
            'rating' => 'required|integer|between:1,5',
            'title' => 'nullable|string|max:255',
            'comment' => 'nullable|string',
            'media_ids' => 'nullable|array',
            'media_ids.*' => 'integer|exists:media,id',
        ]);

        $mediaIds = $validated['media_ids'] ?? [];

        $updatedReview = $this->reviewService->updateReview(
            $user,
            $review,
            $validated['rating'],
            $validated['title'] ?? null,
            $validated['comment'] ?? null,
            $mediaIds
        );

        return new ProductReviewResource($updatedReview->load(['user', 'media']));
    }
}
