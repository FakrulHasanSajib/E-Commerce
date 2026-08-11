<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\Seo\SeoService;
use Illuminate\Http\Request;

class ArticlePublicController extends Controller
{
    public function __construct(protected SeoService $seoService) {}

    /**
     * List published blog posts.
     */
    public function index(Request $request)
    {
        $categorySlug = $request->input('category');

        $query = Article::where('status', 'published')
            ->with(['category', 'author', 'featuredImage', 'seoMetadata'])
            ->latest('published_at');

        if ($categorySlug) {
            $query->whereHas('category', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        $articles = $query->paginate(15);

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
     * View article details with JSON-LD schema and SEO meta tags.
     */
    public function show(string $slug)
    {
        $article = Article::where('slug', $slug)
            ->where('status', 'published')
            ->with(['category', 'author', 'featuredImage', 'seoMetadata'])
            ->firstOrFail();

        // Increment views count atomically
        $article->increment('views_count');

        $seo = $this->seoService->getSeoTags($article);
        $jsonLd = $this->seoService->generateJsonLdSchema($article);

        return response()->json([
            'data' => $article,
            'seo' => $seo,
            'json_ld' => $jsonLd,
        ]);
    }
}
