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
        // Seed system database first (if system DB is available)
        try {
            if (\Illuminate\Support\Facades\Schema::connection('system')->hasTable('system_settings')) {
                $this->call([
                    SystemDatabaseSeeder::class,
                ]);
            }
        } catch (\Exception $e) {
            $this->command->warn('⚠️  System database not available, skipping system seeding: ' . $e->getMessage());
        }

        // Seed permissions and roles
        $this->call([
            PermissionRoleSeeder::class,
        ]);

        // Get roles for later use
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        $superAdminRole = Role::where('name', 'super_admin')->where('guard_name', 'web')->first();
        $permissions = Permission::where('guard_name', 'web')->pluck('name')->toArray();

        // ============================================
        // Create Super Admin User (from FILAMENT_ADMIN_EMAIL)
        // ============================================
        $superAdminEmail = env('FILAMENT_ADMIN_EMAIL', 'admin@example.com');
        $superAdminPassword = env('FILAMENT_ADMIN_PASSWORD', 'SecurePassword123!');
        $superAdminName = env('FILAMENT_ADMIN_NAME', 'Super Admin User');
        
        $this->command->info("Creating super admin user from FILAMENT_ADMIN_EMAIL: {$superAdminEmail}");
        
        // Find or create super admin user
        $superAdminUser = User::firstOrCreate(
            ['email' => $superAdminEmail],
            [
                'name' => $superAdminName,
                'password' => Hash::make($superAdminPassword),
                'email_verified_at' => now(),
            ]
        );
        
        // Update user details if they exist
        $wasRecentlyCreated = $superAdminUser->wasRecentlyCreated;
        if (! $wasRecentlyCreated) {
            if ($superAdminUser->name !== $superAdminName) {
                $superAdminUser->name = $superAdminName;
            }
            
            // Always update password to match environment
            $superAdminUser->password = Hash::make($superAdminPassword);
            
            if (! $superAdminUser->email_verified_at) {
                $superAdminUser->email_verified_at = now();
            }
            
            $superAdminUser->save();
        }

        // Assign super_admin role (includes all permissions + database config management)
        $superAdminUser->syncRoles(['super_admin']);
        
        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $superAdminUser->refresh();
        $superAdminUser->load('roles');
        
        // Verify super_admin role assignment
        if (! $superAdminUser->hasRole('super_admin')) {
            $superAdminUser->assignRole('super_admin');
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $superAdminUser->refresh();
            $superAdminUser->load('roles');
        }
        
        $this->command->info("✅ Super admin user '{$superAdminUser->email}' has been assigned super_admin role");

        // ============================================
        // Create Admin User (with default password)
        // ============================================
        $adminEmail = 'admin@amazinginventory.com';
        $adminPassword = 'SecurePassword123!';
        $adminName = 'Admin User';
        
        $this->command->info("Creating admin user: {$adminEmail}");
        
        // Find or create admin user (only if email is different from super admin)
        if ($adminEmail !== $superAdminEmail) {
            $adminUser = User::firstOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => $adminName,
                    'password' => Hash::make($adminPassword),
                    'email_verified_at' => now(),
                ]
            );
            
            // Update user details if they exist
            $wasRecentlyCreated = $adminUser->wasRecentlyCreated;
            if (! $wasRecentlyCreated) {
                if ($adminUser->name !== $adminName) {
                    $adminUser->name = $adminName;
                }
                
                // Always update password to default
                $adminUser->password = Hash::make($adminPassword);
                
                if (! $adminUser->email_verified_at) {
                    $adminUser->email_verified_at = now();
                }
                
                $adminUser->save();
            }

            // Assign admin role
            $adminUser->syncRoles(['admin']);
            
            // Clear permission cache
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            $adminUser->refresh();
            $adminUser->load('roles');
            
            // Verify admin role assignment
            if (! $adminUser->hasRole('admin')) {
                $adminUser->assignRole('admin');
                app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
                $adminUser->refresh();
                $adminUser->load('roles');
            }
            
            $this->command->info("✅ Admin user '{$adminUser->email}' has been assigned admin role");
        } else {
            $this->command->info("⚠️  Admin email matches super admin email. Skipping admin user creation.");
        }
        
        // Verify the admin role has all permissions (except super admin only permissions)
        $adminPermissions = array_filter($permissions, fn($perm) => $perm !== 'manage database configurations');
        $adminRolePermissions = $adminRole->permissions->pluck('name')->toArray();
        $missingPermissions = array_diff($adminPermissions, $adminRolePermissions);
        if (!empty($missingPermissions)) {
            $adminRole->givePermissionTo($missingPermissions);
        }
        
        // Verify the super_admin role has all permissions
        $superAdminRolePermissions = $superAdminRole->permissions->pluck('name')->toArray();
        $missingSuperAdminPermissions = array_diff($permissions, $superAdminRolePermissions);
        if (!empty($missingSuperAdminPermissions)) {
            $superAdminRole->givePermissionTo($missingSuperAdminPermissions);
        }
    }
}
