<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $fillable = [
        'folder_id',
        'uploaded_by',
        'name',
        'filename',
        'path',
        'disk',
        'mime_type',
        'size',
        'width',
        'height',
        'hash',
        'alt_text',
        'seo_title',
        'seo_description',
        'conversions',
    ];

    /**
     * Get the folder that contains this media file.
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /**
     * Get the user who uploaded this media file.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the tags associated with this media file.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'media_tag');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'conversions' => 'array',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (Media $media) {
            $disk = Storage::disk($media->disk);

            // Delete original file
            $filePath = $media->path.'/'.$media->filename;
            if ($disk->exists($filePath)) {
                $disk->delete($filePath);
            }

            // Delete optimized conversion files
            if ($media->conversions && is_array($media->conversions)) {
                foreach ($media->conversions as $conversion) {
                    if (isset($conversion['path']) && $disk->exists($conversion['path'])) {
                        $disk->delete($conversion['path']);
                    }
                }
            }
        });
    }
}
