<?php

namespace App\Services\Review;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    /**
     * Submit a review for a product.
     */
    public function submitReview(User $user, int $productId, int $rating, ?string $title, ?string $comment, array $mediaIds = []): ProductReview
    {
        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages([
                'rating' => 'Rating must be between 1 and 5.',
            ]);
        }

        // Check if product exists
        $product = Product::findOrFail($productId);

        // Check verified purchaser status
        $isPurchased = Order::where('user_id', $user->id)
            ->where('status', 'delivered')
            ->whereHas('items', function ($q) use ($productId) {
                $q->where('product_id', $productId);
            })
            ->exists();

        // Check for existing review
        $existingReview = ProductReview::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if ($existingReview) {
            if (in_array($existingReview->status, ['approved', 'pending'])) {
                throw ValidationException::withMessages([
                    'product_id' => 'You have already submitted a review for this product.',
                ]);
            }

            // Rejected review resubmission (Upsert pattern)
            $existingReview->update([
                'rating' => $rating,
                'title' => $title,
                'comment' => $comment,
                'status' => 'pending',
                'is_purchased' => $isPurchased,
            ]);

            $review = $existingReview;
        } else {
            $review = ProductReview::create([
                'user_id' => $user->id,
                'product_id' => $productId,
                'rating' => $rating,
                'title' => $title,
                'comment' => $comment,
                'status' => 'pending',
                'is_purchased' => $isPurchased,
            ]);
        }

        $this->syncMedia($review, $mediaIds);

        return $review;
    }

    /**
     * Update an existing review and reset its status to pending.
     */
    public function updateReview(User $user, ProductReview $review, int $rating, ?string $title, ?string $comment, array $mediaIds = []): ProductReview
    {
        if ($review->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'review' => 'Unauthorized action.',
            ]);
        }

        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages([
                'rating' => 'Rating must be between 1 and 5.',
            ]);
        }

        $review->update([
            'rating' => $rating,
            'title' => $title,
            'comment' => $comment,
            'status' => 'pending',
        ]);

        $this->syncMedia($review, $mediaIds);

        // Recalculate ratings in case the previous status was approved
        $this->updateProductRatingCache($review->product_id);

        return $review;
    }

    /**
     * Approve a review.
     */
    public function approveReview(ProductReview $review): void
    {
        $review->update(['status' => 'approved']);
        $this->updateProductRatingCache($review->product_id);
    }

    /**
     * Reject a review.
     */
    public function rejectReview(ProductReview $review): void
    {
        $review->update(['status' => 'rejected']);
        $this->updateProductRatingCache($review->product_id);
    }

    /**
     * Delete a review.
     */
    public function deleteReview(ProductReview $review): void
    {
        $productId = $review->product_id;
        $review->delete();
        $this->updateProductRatingCache($productId);
    }

    /**
     * Recalculate rating caches on the Product model.
     */
    public function updateProductRatingCache(int $productId): void
    {
        $product = Product::findOrFail($productId);

        $stats = DB::table('product_reviews')
            ->where('product_id', $productId)
            ->where('status', 'approved')
            ->selectRaw('COUNT(id) as total_count, AVG(rating) as avg_rating')
            ->first();

        $approvedCount = (int) ($stats->total_count ?? 0);
        $avgRating = $approvedCount > 0 ? (float) ($stats->avg_rating ?? 0.00) : 0.00;

        // Use Eloquent update to trigger Laravel Scout reindexing
        $product->update([
            'average_rating' => $avgRating,
            'reviews_count' => $approvedCount,
            'review_count' => $approvedCount, // Keep legacy field in sync
        ]);
    }

    /**
     * Sync polymorphic media attachments for the review.
     */
    protected function syncMedia(ProductReview $review, array $mediaIds): void
    {
        $syncData = [];
        foreach ($mediaIds as $index => $mediaId) {
            $syncData[$mediaId] = [
                'mediable_type' => ProductReview::class,
                'collection_name' => 'review_media',
                'order' => $index + 1,
            ];
        }

        $review->media()->sync($syncData);
    }
}
