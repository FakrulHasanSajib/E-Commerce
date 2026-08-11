<?php

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed default roles and permissions
    $this->seed(RbacSeeder::class);

    // Get seeded users or create them
    $this->superAdmin = User::where('email', 'admin@larashop.test')->first() ?? User::factory()->create([
        'email' => 'admin@larashop.test',
    ]);

    // Ensure super-admin role is assigned
    if (! $this->superAdmin->hasRole('super-admin')) {
        $this->superAdmin->assignRole('super-admin');
    }

    $this->manager = User::factory()->create(['email' => 'manager@larashop.test']);
    $this->manager->assignRole('manager');

    $this->staff = User::factory()->create(['email' => 'staff@larashop.test']);
    $this->staff->assignRole('staff');

    // Create a staff user that has manage-roles permission directly or via role
    $this->roleAdmin = User::factory()->create(['email' => 'role-admin@larashop.test']);
    $this->roleAdmin->givePermissionTo('manage-roles');
});

test('unauthorized users cannot access RBAC endpoints', function () {
    // Managers do not have manage-roles permission
    $this->actingAs($this->manager)
        ->getJson('/api/admin/roles')
        ->assertStatus(403);

    $this->actingAs($this->manager)
        ->getJson('/api/admin/permissions')
        ->assertStatus(403);
});

test('authorized users can list roles and permissions', function () {
    $this->actingAs($this->roleAdmin)
        ->getJson('/api/admin/roles')
        ->assertStatus(200)
        ->assertJsonCount(4, 'data'); // super-admin, administrator, manager, staff

    $this->actingAs($this->roleAdmin)
        ->getJson('/api/admin/permissions')
        ->assertStatus(200)
        ->assertJsonCount(6, 'data');
});

test('super admin bypasses all gates automatically', function () {
    // Define an unseeded dummy gate
    Gate::define('dummy-gate-123', fn () => false);

    // Manager should be denied
    expect(Gate::forUser($this->manager)->allows('dummy-gate-123'))->toBeFalse();

    // Super Admin should be allowed due to Gate::before bypass
    expect(Gate::forUser($this->superAdmin)->allows('dummy-gate-123'))->toBeTrue();
});

test('admin can manage custom roles and sync permissions', function () {
    // 1. Create custom role
    $response = $this->actingAs($this->roleAdmin)
        ->postJson('/api/admin/roles', [
            'name' => 'content-editor',
            'permissions' => ['manage-media', 'manage-products'],
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'content-editor')
        ->assertJsonCount(2, 'data.permissions');

    $roleId = $response->json('data.id');

    // 2. Update role name and permissions
    $this->actingAs($this->roleAdmin)
        ->putJson("/api/admin/roles/{$roleId}", [
            'name' => 'editor-senior',
            'permissions' => ['manage-media'],
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'editor-senior')
        ->assertJsonCount(1, 'data.permissions');
});

test('system protected roles cannot be renamed, deleted, or have super-admin modified', function () {
    $superAdminRole = Role::findByName('super-admin', 'sanctum');
    $managerRole = Role::findByName('manager', 'sanctum');

    // 1. Try to rename a system role
    $this->actingAs($this->roleAdmin)
        ->putJson("/api/admin/roles/{$managerRole->id}", [
            'name' => 'renamed-manager',
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'System protected roles cannot be renamed.']);

    // 2. Try to modify super-admin permissions
    $this->actingAs($this->roleAdmin)
        ->putJson("/api/admin/roles/{$superAdminRole->id}", [
            'permissions' => ['manage-media'],
        ])
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'The super-admin role permissions cannot be modified.']);

    // 3. Try to delete a system role
    $this->actingAs($this->roleAdmin)
        ->deleteJson("/api/admin/roles/{$managerRole->id}")
        ->assertStatus(422)
        ->assertJsonFragment(['message' => 'System protected roles cannot be deleted.']);
});

test('admin can delete a custom role', function () {
    $customRole = Role::create(['name' => 'designer']);

    $this->actingAs($this->roleAdmin)
        ->deleteJson("/api/admin/roles/{$customRole->id}")
        ->assertStatus(204);

    expect(Role::find($customRole->id))->toBeNull();
});

test('admin can sync user roles and direct permissions', function () {
    // 1. Sync User Roles
    $this->actingAs($this->roleAdmin)
        ->patchJson("/api/admin/users/{$this->staff->id}/roles", [
            'roles' => ['manager', 'staff'],
        ])
        ->assertStatus(200)
        ->assertJsonFragment(['roles' => ['manager', 'staff']]);

    expect($this->staff->fresh()->hasRole('manager'))->toBeTrue();

    // 2. Sync User Direct Permissions
    $this->actingAs($this->roleAdmin)
        ->patchJson("/api/admin/users/{$this->staff->id}/permissions", [
            'permissions' => ['manage-settings'],
        ])
        ->assertStatus(200);

    expect($this->staff->fresh()->hasDirectPermission('manage-settings'))->toBeTrue();
});

test('Spatie permission cache invalidates on changes', function () {
    $staffUser = $this->staff;

    // Staff starts with no manage-settings permission
    expect(Gate::forUser($staffUser)->allows('manage-settings'))->toBeFalse();

    // Give permission (this should trigger Spatie cache invalidation automatically)
    $this->actingAs($this->roleAdmin)
        ->patchJson("/api/admin/users/{$staffUser->id}/permissions", [
            'permissions' => ['manage-settings'],
        ])
        ->assertStatus(200);

    // Refresh gate and check permission -> must return true immediately
    expect(Gate::forUser($staffUser->fresh())->allows('manage-settings'))->toBeTrue();
});
