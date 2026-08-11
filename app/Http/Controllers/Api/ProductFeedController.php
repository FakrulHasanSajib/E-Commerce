<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Marketing\ProductFeedService;

class ProductFeedController extends Controller
{
    public function __construct(protected ProductFeedService $productFeedService) {}

    /**
     * Render Google Shopping XML product feed.
     */
    public function googleShopping()
    {
        $xml = $this->productFeedService->generateGoogleShoppingXml();

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    /**
     * Render Meta Catalog CSV product feed.
     */
    public function facebookCatalog()
    {
        $csv = $this->productFeedService->generateFacebookCatalogCsv();

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
