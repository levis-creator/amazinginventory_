<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create default permissions
        $permissions = [
            'view users',
            'create users',
            'update users',
            'delete users',
            'view roles',
            'create roles',
            'update roles',
            'delete roles',
            'view permissions',
            'create permissions',
            'update permissions',
            'delete permissions',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }

        // Create admin role and assign all permissions
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web']
        );
        $adminRole->syncPermissions($permissions);

        // Create user role with limited permissions
        $userRole = Role::firstOrCreate(
            ['name' => 'user', 'guard_name' => 'web']
        );
        $userRole->givePermissionTo(['view users']);

        // Create Filament admin user from environment variables
        $adminEmail = env('FILAMENT_ADMIN_EMAIL', 'admin@example.com');
        $adminPassword = env('FILAMENT_ADMIN_PASSWORD', 'password');
        $adminName = env('FILAMENT_ADMIN_NAME', 'Admin User');
        
        // Find or create admin user
        $adminUser = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
            ]
        );
        
        // Update user details if they exist (name, password, email verification)
        $wasRecentlyCreated = $adminUser->wasRecentlyCreated;
        if (! $wasRecentlyCreated) {
            // Update name if changed
            if ($adminUser->name !== $adminName) {
                $adminUser->name = $adminName;
            }
            
            // Update password if changed in environment
            if ($adminPassword !== 'password') {
                $adminUser->password = Hash::make($adminPassword);
            }
            
            // Ensure email is verified
            if (! $adminUser->email_verified_at) {
                $adminUser->email_verified_at = now();
            }
            
            $adminUser->save();
        }

        // Ensure admin user has admin role (which includes all permissions)
        // Use syncRoles to remove any other roles and ensure only admin role is assigned
        $adminUser->syncRoles(['admin']);
        
        // Double-check: if for some reason the role wasn't assigned, assign it explicitly
        if (! $adminUser->hasRole('admin')) {
            $adminUser->assignRole('admin');
        }
        
        // Verify the admin role has all permissions
        $adminRolePermissions = $adminRole->permissions->pluck('name')->toArray();
        $missingPermissions = array_diff($permissions, $adminRolePermissions);
        if (!empty($missingPermissions)) {
            // If any permissions are missing from the admin role, add them
            $adminRole->givePermissionTo($missingPermissions);
        }
    }
}
