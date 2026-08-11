<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CropImageRequest;
use App\Http\Requests\Admin\StoreMediaRequest;
use App\Http\Requests\Admin\UpdateMediaRequest;
use App\Http\Resources\Admin\MediaResource;
use App\Models\Media;
use App\Models\Tag;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function __construct(protected MediaService $mediaService)
    {
        //
    }

    /**
     * Display a paginated listing of media assets, filtered by folder, tag, mime type, or search term.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Media::with(['folder', 'tags']);

        // Filter by folder
        if ($request->has('folder_id')) {
            $folderId = $request->input('folder_id');
            if ($folderId === 'root' || $folderId === '') {
                $query->whereNull('folder_id');
            } else {
                $query->where('folder_id', $folderId);
            }
        }

        // Filter by tag
        if ($request->filled('tag')) {
            $tag = $request->input('tag');
            $query->whereHas('tags', function ($q) use ($tag) {
                $q->where('slug', Str::slug($q->raw($tag)))
                    ->orWhere('name', $tag);
            });
        }

        // Filter by mime type (e.g. image, video, pdf)
        if ($request->filled('mime_type')) {
            $mimeType = $request->input('mime_type');
            if (in_array($mimeType, ['image', 'video', 'pdf'])) {
                if ($mimeType === 'pdf') {
                    $query->where('mime_type', 'application/pdf');
                } else {
                    $query->where('mime_type', 'like', "{$mimeType}/%");
                }
            } else {
                $query->where('mime_type', $mimeType);
            }
        }

        // Search in original file name
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $query->orderBy('created_at', 'desc');

        return MediaResource::collection($query->paginate(15));
    }

    /**
     * Store uploaded media files. Supports drag-drop single or bulk multi-file upload.
     */
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $folderId = $request->input('folder_id');
        $uploader = $request->user();

        // Process tags if provided
        $tagIds = [];
        if ($request->has('tags')) {
            $tagIds = collect($request->input('tags'))->map(function ($tagName) {
                $tag = Tag::firstOrCreate([
                    'name' => $tagName,
                ], [
                    'slug' => Str::slug($tagName),
                ]);

                return $tag->id;
            })->toArray();
        }

        $uploadedMedia = [];

        // Bulk Uploads
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $media = $this->mediaService->upload($file, $folderId, $uploader);
                if (! empty($tagIds)) {
                    $media->tags()->syncWithoutDetaching($tagIds);
                }
                $uploadedMedia[] = $media->load(['folder', 'tags']);
            }

            return response()->json([
                'data' => MediaResource::collection($uploadedMedia),
            ], 201);
        }

        // Single Upload
        if ($request->hasFile('file')) {
            $media = $this->mediaService->upload($request->file('file'), $folderId, $uploader);
            if (! empty($tagIds)) {
                $media->tags()->syncWithoutDetaching($tagIds);
            }
            $media->load(['folder', 'tags']);

            return (new MediaResource($media))
                ->response()
                ->setStatusCode(201);
        }

        return response()->json(['message' => 'No files uploaded.'], 422);
    }

    /**
     * Update media details (name, folder, alt/SEO text, tags).
     */
    public function update(UpdateMediaRequest $request, int $id): MediaResource
    {
        $media = Media::findOrFail($id);
        $data = $request->only(['name', 'folder_id', 'alt_text', 'seo_title', 'seo_description']);

        $media->update($data);

        // Sync tags if provided
        if ($request->has('tags')) {
            $tagIds = collect($request->input('tags'))->map(function ($tagName) {
                $tag = Tag::firstOrCreate([
                    'name' => $tagName,
                ], [
                    'slug' => Str::slug($tagName),
                ]);

                return $tag->id;
            })->toArray();

            $media->tags()->sync($tagIds);
        }

        $media->load(['folder', 'tags']);

        return new MediaResource($media);
    }

    /**
     * Bulk delete media files.
     */
    public function destroy(Request $request): Response|JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:media,id',
        ]);

        $mediaFiles = Media::whereIn('id', $validated['ids'])->get();

        foreach ($mediaFiles as $media) {
            $media->delete(); // Model observer automatically deletes storage files
        }

        return response()->noContent();
    }

    /**
     * Crop, resize, or rotate an image.
     */
    public function crop(CropImageRequest $request, int $id): MediaResource
    {
        $media = Media::findOrFail($id);

        $edited = $this->mediaService->editImage($media, $request->validated());

        return new MediaResource($edited->load(['folder', 'tags']));
    }
}
