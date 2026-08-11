<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MediaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $disk = Storage::disk($this->disk);
        $originalUrl = $disk->url($this->path.'/'.$this->filename);

        $formattedConversions = [];
        if ($this->conversions && is_array($this->conversions)) {
            foreach ($this->conversions as $name => $conv) {
                if (isset($conv['path'])) {
                    $formattedConversions[$name] = [
                        'url' => $disk->url($conv['path']),
                        'width' => $conv['width'] ?? null,
                        'height' => $conv['height'] ?? null,
                        'size' => $conv['size'] ?? null,
                    ];
                }
            }
        }

        return [
            'id' => $this->id,
            'folder_id' => $this->folder_id,
            'folder' => new MediaFolderResource($this->whenLoaded('folder')),
            'uploaded_by' => $this->uploaded_by,
            'name' => $this->name,
            'filename' => $this->filename,
            'url' => $originalUrl,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'hash' => $this->hash,
            'alt_text' => $this->alt_text,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'tags' => $this->relationLoaded('tags') ? $this->tags->pluck('name') : $this->tags()->pluck('name'),
            'conversions' => $formattedConversions,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
