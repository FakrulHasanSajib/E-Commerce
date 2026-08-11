<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Seo\SeoService;

class SitemapController extends Controller
{
    public function __construct(protected SeoService $seoService) {}

    /**
     * Render Sitemap Index XML.
     */
    public function index()
    {
        $xml = $this->seoService->generateSitemapIndexXml();

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    /**
     * Render modular sub-sitemap XML.
     */
    public function subSitemap(string $type)
    {
        if (! in_array($type, ['products', 'categories', 'articles', 'pages'])) {
            return response('Sitemap type not found', 404);
        }

        $xml = $this->seoService->generateSubSitemapXml($type);

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
