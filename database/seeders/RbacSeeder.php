<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create Permissions
        $permissions = [
            'manage-settings',
            'manage-media',
            'manage-users',
            'manage-roles',
            'manage-products',
            'manage-orders',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'sanctum']);
        }

        // 2. Create Roles and Assign Permissions
        // Super Admin
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'sanctum']);

        // Administrator (gets all permissions)
        $adminRole = Role::firstOrCreate(['name' => 'administrator', 'guard_name' => 'sanctum']);
        $adminRole->syncPermissions(Permission::all());

        // Manager (gets manage-products and manage-orders)
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum']);
        $managerRole->syncPermissions(['manage-products', 'manage-orders']);

        // Staff (empty default permissions, custom overrides assigned later)
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);

        // 3. Assign Super Admin role to the main admin user
        $adminUser = User::where('email', 'admin@larashop.test')->first();
        if ($adminUser) {
            $adminUser->assignRole('super-admin');
        }
    }
}
