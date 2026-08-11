<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandPublicController extends Controller
{
    /**
     * Display a listing of active brands for the storefront.
     */
    public function index(): AnonymousResourceCollection
    {
        $brands = Brand::with('logo')
            ->where('is_active', true)
            ->paginate(15);

        return BrandResource::collection($brands);
    }
}
