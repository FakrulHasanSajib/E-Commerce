<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PermissionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Display a listing of all system permissions.
     */
    public function index(): AnonymousResourceCollection
    {
        $permissions = Permission::all();

        return PermissionResource::collection($permissions);
    }
}
