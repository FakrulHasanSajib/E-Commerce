<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Get the media files associated with this tag.
     */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'media_tag');
    }
}
