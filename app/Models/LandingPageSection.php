<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'landing_page_id',
    'type',
    'title',
    'content',
    'is_visible',
    'position',
    'device_visibility',
])]
class LandingPageSection extends Model
{
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_visible' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }

    /**
     * Polymorphic media attachment relation.
     */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'mediables', 'mediable_id', 'media_id')
            ->wherePivot('mediable_type', static::class)
            ->withPivot('collection_name', 'order', 'alt_text');
    }
}
