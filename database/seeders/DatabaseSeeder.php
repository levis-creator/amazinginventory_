<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
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
        $adminUser = User::firstOrCreate(
            ['email' => env('FILAMENT_ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('FILAMENT_ADMIN_NAME', 'Admin User'),
                'password' => env('FILAMENT_ADMIN_PASSWORD', 'password'),
                'email_verified_at' => now(),
            ]
        );

        // Assign admin role to admin user
        if (! $adminUser->hasRole('admin')) {
            $adminUser->assignRole('admin');
        }
    }
}
