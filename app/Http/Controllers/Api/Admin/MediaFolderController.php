<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MediaFolderResource;
use App\Models\MediaFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MediaFolderController extends Controller
{
    /**
     * Display a listing of the root folders with their child folders.
     */
    public function index(): AnonymousResourceCollection
    {
        $folders = MediaFolder::with('children')
            ->whereNull('parent_id')
            ->withCount('media')
            ->get();

        return MediaFolderResource::collection($folders);
    }

    /**
     * Store a newly created folder.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'parent_id' => 'nullable|integer|exists:media_folders,id',
        ]);

        $folder = MediaFolder::create($validated);

        return (new MediaFolderResource($folder))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified folder.
     */
    public function show(int $id): MediaFolderResource
    {
        $folder = MediaFolder::with(['children', 'media.tags'])
            ->withCount('media')
            ->findOrFail($id);

        return new MediaFolderResource($folder);
    }

    /**
     * Update the specified folder.
     */
    public function update(Request $request, int $id): MediaFolderResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'parent_id' => "nullable|integer|exists:media_folders,id|different:{$id}",
        ]);

        $folder = MediaFolder::findOrFail($id);
        $folder->update($validated);

        return new MediaFolderResource($folder);
    }

    /**
     * Remove the specified folder.
     */
    public function destroy(int $id): JsonResponse|Response
    {
        $folder = MediaFolder::findOrFail($id);

        if (! $folder->isEmpty()) {
            return response()->json([
                'message' => 'Folder cannot be deleted because it contains files or subfolders.',
            ], 422);
        }

        $folder->delete();

        return response()->noContent();
    }
}
