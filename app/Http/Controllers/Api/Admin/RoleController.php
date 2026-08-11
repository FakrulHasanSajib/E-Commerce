<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    protected array $systemRoles = ['super-admin', 'administrator', 'manager', 'staff'];

    /**
     * Display a listing of all roles.
     */
    public function index(): AnonymousResourceCollection
    {
        $roles = Role::with('permissions')->get();

        return RoleResource::collection($roles);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'sanctum',
        ]);

        if (! empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return (new RoleResource($role->load('permissions')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified role.
     */
    public function show(int $id): RoleResource
    {
        $role = Role::with('permissions')->findOrFail($id);

        return new RoleResource($role);
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, int $id): JsonResponse|RoleResource
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => "sometimes|required|string|max:50|unique:roles,name,{$id}",
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        // Protect system roles from modifications
        if (in_array($role->name, $this->systemRoles)) {
            // Cannot rename
            if (isset($validated['name']) && $validated['name'] !== $role->name) {
                return response()->json([
                    'message' => 'System protected roles cannot be renamed.',
                ], 422);
            }
            // Cannot modify super-admin permissions (bypassed anyway)
            if ($role->name === 'super-admin' && isset($validated['permissions'])) {
                return response()->json([
                    'message' => 'The super-admin role permissions cannot be modified.',
                ], 422);
            }
        } else {
            if (isset($validated['name'])) {
                $role->name = $validated['name'];
                $role->save();
            }
        }

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return new RoleResource($role->load('permissions'));
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(int $id): Response|JsonResponse
    {
        $role = Role::findOrFail($id);

        if (in_array($role->name, $this->systemRoles)) {
            return response()->json([
                'message' => 'System protected roles cannot be deleted.',
            ], 422);
        }

        $role->delete();

        return response()->noContent();
    }
}
