<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'user_id',
    'product_id',
    'rating',
    'title',
    'comment',
    'status',
    'is_purchased',
])]
class ProductReview extends Model
{
    /**
     * Casts array.
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_purchased' => 'boolean',
        ];
    }

    /**
     * Get user who submitted review.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get product linked to review.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get polymorphic media attachments.
     */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'mediables', 'mediable_id', 'media_id')
            ->wherePivot('mediable_type', static::class)
            ->withPivot('collection_name', 'order', 'alt_text');
    }
}
