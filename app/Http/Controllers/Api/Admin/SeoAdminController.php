<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotFoundLog;
use App\Models\UrlRedirect;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SeoAdminController extends Controller
{
    /**
     * List 301/302 redirects.
     */
    public function redirects()
    {
        $redirects = UrlRedirect::latest()->paginate(15);

        return response()->json([
            'data' => $redirects->items(),
            'meta' => [
                'current_page' => $redirects->currentPage(),
                'last_page' => $redirects->lastPage(),
                'total' => $redirects->total(),
            ],
        ]);
    }

    /**
     * Store a 301/302 redirect (with loop prevention).
     */
    public function storeRedirect(Request $request)
    {
        $validated = $request->validate([
            'source_url' => 'required|string|max:255|unique:url_redirects,source_url',
            'target_url' => 'required|string|max:255',
            'status_code' => 'nullable|integer|in:301,302',
            'is_active' => 'nullable|boolean',
        ]);

        $source = '/'.ltrim($validated['source_url'], '/');
        $target = '/'.ltrim($validated['target_url'], '/');

        // Loop Prevention Guard
        if ($source === $target) {
            throw ValidationException::withMessages([
                'target_url' => 'Source URL and Target URL cannot be identical.',
            ]);
        }

        if (UrlRedirect::where('source_url', $target)->exists()) {
            throw ValidationException::withMessages([
                'target_url' => 'Target URL is already an active redirect source (prevents infinite redirect loops).',
            ]);
        }

        $redirect = UrlRedirect::create([
            'source_url' => $source,
            'target_url' => $target,
            'status_code' => $validated['status_code'] ?? 301,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Redirect created successfully.',
            'data' => $redirect,
        ], 201);
    }

    /**
     * Update a redirect.
     */
    public function updateRedirect(Request $request, $id)
    {
        $redirect = UrlRedirect::findOrFail($id);

        $validated = $request->validate([
            'source_url' => "nullable|string|max:255|unique:url_redirects,source_url,{$id}",
            'target_url' => 'nullable|string|max:255',
            'status_code' => 'nullable|integer|in:301,302',
            'is_active' => 'nullable|boolean',
        ]);

        $source = isset($validated['source_url']) ? '/'.ltrim($validated['source_url'], '/') : $redirect->source_url;
        $target = isset($validated['target_url']) ? '/'.ltrim($validated['target_url'], '/') : $redirect->target_url;

        if ($source === $target) {
            throw ValidationException::withMessages([
                'target_url' => 'Source URL and Target URL cannot be identical.',
            ]);
        }

        $redirect->update([
            'source_url' => $source,
            'target_url' => $target,
            'status_code' => $validated['status_code'] ?? $redirect->status_code,
            'is_active' => array_key_exists('is_active', $validated) ? $validated['is_active'] : $redirect->is_active,
        ]);

        return response()->json([
            'message' => 'Redirect updated successfully.',
            'data' => $redirect,
        ]);
    }

    /**
     * Delete a redirect.
     */
    public function destroyRedirect($id)
    {
        $redirect = UrlRedirect::findOrFail($id);
        $redirect->delete();

        return response()->json(null, 204);
    }

    /**
     * List 404 not found hit logs.
     */
    public function notFoundLogs()
    {
        $logs = NotFoundLog::latest('last_occurred_at')->paginate(15);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
