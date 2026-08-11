<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAccessController extends Controller
{
    /**
     * Sync roles for the specified user.
     */
    public function syncRoles(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        $user = User::findOrFail($id);
        $user->syncRoles($validated['roles']);

        return response()->json([
            'message' => 'User roles updated successfully.',
            'roles' => $user->getRoleNames(),
        ]);
    }

    /**
     * Sync direct permissions for the specified user.
     */
    public function syncPermissions(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $user = User::findOrFail($id);
        $user->syncPermissions($validated['permissions']);

        return response()->json([
            'message' => 'User direct permissions updated successfully.',
            'permissions' => $user->getPermissionNames(),
        ]);
    }
}
