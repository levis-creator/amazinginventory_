<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionRoleSeeder extends Seeder
{
    /**
     * Seed the application's permissions and roles.
     */
    public function run(): void
    {
        // Define all permissions for the application
        $permissions = [
            // User management permissions
            'view users',
            'create users',
            'update users',
            'delete users',
            
            // Role management permissions
            'view roles',
            'create roles',
            'update roles',
            'delete roles',
            
            // Permission management permissions
            'view permissions',
            'create permissions',
            'update permissions',
            'delete permissions',
        ];

        // Create all permissions
        $this->command->info('Creating permissions...');
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                ['name' => $permission, 'guard_name' => 'web']
            );
            $this->command->line("  ✓ Created/verified permission: {$permission}");
        }

        // Create admin role and assign all permissions
        $this->command->info('Creating admin role...');
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['name' => 'admin', 'guard_name' => 'web']
        );
        $adminRole->syncPermissions($permissions);
        $this->command->info("  ✓ Admin role created/verified with " . count($permissions) . " permissions");

        // Create user role with limited permissions
        $this->command->info('Creating user role...');
        $userRole = Role::firstOrCreate(
            ['name' => 'user', 'guard_name' => 'web'],
            ['name' => 'user', 'guard_name' => 'web']
        );
        $userRole->syncPermissions(['view users']);
        $this->command->info("  ✓ User role created/verified with limited permissions");

        // Verify admin role has all permissions
        $adminRolePermissions = $adminRole->permissions->pluck('name')->toArray();
        $missingPermissions = array_diff($permissions, $adminRolePermissions);
        if (!empty($missingPermissions)) {
            $this->command->warn('  ⚠ Adding missing permissions to admin role...');
            $adminRole->givePermissionTo($missingPermissions);
            $this->command->info("  ✓ Added " . count($missingPermissions) . " missing permissions to admin role");
        }

        $this->command->info('✅ Permissions and roles seeded successfully!');
        $this->command->newLine();
        $this->command->info('Summary:');
        $this->command->line("  - Permissions: " . count($permissions));
        $this->command->line("  - Roles: 2 (admin, user)");
        $this->command->line("  - Admin role permissions: " . $adminRole->permissions->count());
        $this->command->line("  - User role permissions: " . $userRole->permissions->count());
    }
}

