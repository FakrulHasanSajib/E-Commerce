<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

#[Fillable([
    'title',
    'slug',
    'status',
    'template',
    'published_at',
    'header_settings',
    'footer_settings',
    'layout_settings',
])]
class LandingPage extends Model
{
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'header_settings' => 'array',
            'footer_settings' => 'array',
            'layout_settings' => 'array',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(LandingPageSection::class)->orderBy('position', 'asc');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LandingPageVersion::class)->orderBy('version_number', 'desc');
    }

    public function seoMetadata(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }
}
