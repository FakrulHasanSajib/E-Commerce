<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mediaUrls = [];
        if ($this->relationLoaded('media')) {
            foreach ($this->media as $item) {
                $mediaUrls[] = [
                    'id' => $item->id,
                    'url' => Storage::disk($item->disk)->url($item->path.'/'.$item->filename),
                    'order' => $item->pivot->order,
                ];
            }
        }

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'rating' => $this->rating,
            'title' => $this->title,
            'comment' => $this->comment,
            'status' => $this->status,
            'is_purchased' => $this->is_purchased,
            'created_at' => $this->created_at->toIso8601String(),
            'user' => $this->relationLoaded('user') ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'media' => $mediaUrls,
        ];
    }
}
