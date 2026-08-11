<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'media_id',
    'name',
    'download_limit',
    'expiry_days',
])]
class ProductDownload extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'download_limit' => 'integer',
            'expiry_days' => 'integer',
        ];
    }

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the downloadable file asset.
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
