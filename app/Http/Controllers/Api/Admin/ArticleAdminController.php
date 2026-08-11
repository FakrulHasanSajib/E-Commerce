<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ArticleAdminController extends Controller
{
    /**
     * List all articles.
     */
    public function index()
    {
        $articles = Article::with(['category', 'author', 'featuredImage', 'seoMetadata'])->latest()->paginate(15);

        return response()->json([
            'data' => $articles->items(),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'total' => $articles->total(),
            ],
        ]);
    }

    /**
     * Display the specified article details.
     */
    public function show($id)
    {
        $article = Article::with(['category', 'author', 'featuredImage', 'seoMetadata'])->findOrFail($id);

        return response()->json([
            'data' => $article,
        ]);
    }

    /**
     * Create a new article.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:article_categories,id',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:articles,slug',
            'excerpt' => 'nullable|string',
            'body' => 'required|string',
            'featured_image_id' => 'nullable|integer|exists:media,id',
            'status' => 'nullable|string|in:draft,published',
            'published_at' => 'nullable|date',
            'seo' => 'nullable|array',
        ]);

        $user = $request->user('sanctum');

        $article = Article::create([
            'category_id' => $validated['category_id'] ?? null,
            'author_id' => $user?->id,
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'excerpt' => $validated['excerpt'] ?? null,
            'body' => $validated['body'],
            'featured_image_id' => $validated['featured_image_id'] ?? null,
            'status' => $validated['status'] ?? 'draft',
            'published_at' => isset($validated['published_at']) ? Carbon::parse($validated['published_at']) : Carbon::now(),
        ]);

        if (! empty($validated['seo'])) {
            $article->seoMetadata()->create($validated['seo']);
        }

        return response()->json([
            'message' => 'Article created successfully.',
            'data' => $article->load(['category', 'featuredImage', 'seoMetadata']),
        ], 201);
    }

    /**
     * Update an article.
     */
    public function update(Request $request, $id)
    {
        $article = Article::findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:article_categories,id',
            'title' => 'nullable|string|max:255',
            'slug' => "nullable|string|max:255|unique:articles,slug,{$id}",
            'excerpt' => 'nullable|string',
            'body' => 'nullable|string',
            'featured_image_id' => 'nullable|integer|exists:media,id',
            'status' => 'nullable|string|in:draft,published',
            'published_at' => 'nullable|date',
            'seo' => 'nullable|array',
        ]);

        $article->update([
            'category_id' => array_key_exists('category_id', $validated) ? $validated['category_id'] : $article->category_id,
            'title' => $validated['title'] ?? $article->title,
            'slug' => $validated['slug'] ?? $article->slug,
            'excerpt' => array_key_exists('excerpt', $validated) ? $validated['excerpt'] : $article->excerpt,
            'body' => $validated['body'] ?? $article->body,
            'featured_image_id' => array_key_exists('featured_image_id', $validated) ? $validated['featured_image_id'] : $article->featured_image_id,
            'status' => $validated['status'] ?? $article->status,
            'published_at' => isset($validated['published_at']) ? Carbon::parse($validated['published_at']) : $article->published_at,
        ]);

        if (array_key_exists('seo', $validated) && is_array($validated['seo'])) {
            $article->seoMetadata()->updateOrCreate([], $validated['seo']);
        }

        return response()->json([
            'message' => 'Article updated successfully.',
            'data' => $article->load(['category', 'featuredImage', 'seoMetadata']),
        ]);
    }

    /**
     * Delete an article.
     */
    public function destroy($id)
    {
        $article = Article::findOrFail($id);
        $article->delete();

        return response()->json(null, 204);
    }
}
