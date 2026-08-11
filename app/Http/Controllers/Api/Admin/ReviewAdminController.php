<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductReviewResource;
use App\Models\ProductReview;
use App\Services\Review\ReviewService;
use Illuminate\Http\Request;

class ReviewAdminController extends Controller
{
    public function __construct(protected ReviewService $reviewService) {}

    /**
     * List all reviews with status filters.
     */
    public function index(Request $request)
    {
        $status = $request->input('status');

        $query = ProductReview::with(['user', 'product', 'media'])->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $reviews = $query->paginate(15);

        return ProductReviewResource::collection($reviews);
    }

    /**
     * Update review status (approve or reject).
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:approved,rejected',
        ]);

        $review = ProductReview::findOrFail($id);

        if ($request->status === 'approved') {
            $this->reviewService->approveReview($review);
        } else {
            $this->reviewService->rejectReview($review);
        }

        return response()->json([
            'message' => "Review {$request->status} successfully.",
        ], 200);
    }

    /**
     * Delete review.
     */
    public function destroy($id)
    {
        $review = ProductReview::findOrFail($id);

        $this->reviewService->deleteReview($review);

        return response()->json(null, 204);
    }
}
