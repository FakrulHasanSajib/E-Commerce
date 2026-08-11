<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AuditLogService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleAdminController extends Controller
{
    public function __construct(protected AuditLogService $auditLogService) {}

    /**
     * List all roles.
     */
    public function roles()
    {
        $roles = Role::all();

        return response()->json(['data' => $roles]);
    }

    /**
     * Assign role to a user.
     */
    public function assignRole(Request $request, $userId)
    {
        $validated = $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = User::findOrFail($userId);
        $oldRoles = $user->getRoleNames()->toArray();

        $user->syncRoles([$validated['role']]);

        $this->auditLogService->log(
            'user.role_assigned',
            $user,
            ['old_roles' => $oldRoles, 'new_role' => $validated['role']],
            "Assigned role {$validated['role']} to user {$user->email}."
        );

        return response()->json([
            'message' => 'Role assigned successfully.',
            'data' => $user->load('roles'),
        ]);
    }
}
