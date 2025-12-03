<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class DiagnoseFilamentLogin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'filament:diagnose-login {email?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose Filament login issues on Railway';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Diagnosing Filament Login Issues...');
        $this->newLine();

        // Get email from argument or env
        $email = $this->argument('email') ?? env('FILAMENT_ADMIN_EMAIL', 'admin@example.com');

        // 1. Check sessions table
        $this->info('1. Checking sessions table...');
        if (Schema::hasTable('sessions')) {
            $sessionCount = DB::table('sessions')->count();
            $this->line("   ✓ Sessions table exists ({$sessionCount} sessions)");
        } else {
            $this->error('   ✗ Sessions table does not exist!');
            $this->line('   Run: php artisan migrate');
            return 1;
        }
        $this->newLine();

        // 2. Check user exists
        $this->info('2. Checking admin user...');
        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("   ✗ User with email '{$email}' not found!");
            $this->line('   Run: php artisan db:seed');
            return 1;
        }
        $this->line("   ✓ User found: {$user->name} ({$user->email})");
        $this->newLine();

        // 3. Check user roles
        $this->info('3. Checking user roles...');
        $user->load('roles');
        $roles = $user->roles->pluck('name')->toArray();
        if (empty($roles)) {
            $this->error('   ✗ User has no roles!');
            $this->line('   Assigning admin role...');
            $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
            if ($adminRole) {
                $user->assignRole('admin');
                $this->line('   ✓ Admin role assigned');
            } else {
                $this->error('   ✗ Admin role does not exist!');
                $this->line('   Run: php artisan db:seed');
                return 1;
            }
        } else {
            $this->line('   ✓ User roles: ' . implode(', ', $roles));
        }

        $hasAdminRole = $user->hasRole('admin') || $user->hasRole('super_admin');
        if (!$hasAdminRole) {
            $this->error('   ✗ User does not have admin or super_admin role!');
            $this->line('   Fixing...');
            $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
            if ($adminRole) {
                $user->assignRole('admin');
                $this->line('   ✓ Admin role assigned');
            }
        } else {
            $this->line('   ✓ User has admin/super_admin role');
        }
        $this->newLine();

        // 4. Check canAccessPanel
        $this->info('4. Checking canAccessPanel()...');
        try {
            $canAccess = $user->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'));
            if ($canAccess) {
                $this->line('   ✓ canAccessPanel() returns true');
            } else {
                $this->error('   ✗ canAccessPanel() returns false!');
                $this->line('   This will prevent login even with correct credentials.');
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Error checking canAccessPanel: ' . $e->getMessage());
        }
        $this->newLine();

        // 5. Check session configuration
        $this->info('5. Checking session configuration...');
        $sessionDriver = config('session.driver');
        $sessionSecure = config('session.secure');
        $sessionSameSite = config('session.same_site');
        $sessionDomain = config('session.domain');
        $appUrl = config('app.url');

        $this->line("   Session Driver: {$sessionDriver}");
        $this->line("   Session Secure: " . ($sessionSecure ? 'true' : 'false'));
        $this->line("   Session SameSite: {$sessionSameSite}");
        $this->line("   Session Domain: " . ($sessionDomain ?: 'null'));
        $this->line("   APP_URL: {$appUrl}");

        if ($sessionDriver !== 'database') {
            $this->warn('   ⚠ Session driver is not "database". For Railway, use database sessions.');
        }

        if ($sessionSecure === null) {
            $this->warn('   ⚠ SESSION_SECURE_COOKIE is not set. Should be "true" for HTTPS on Railway.');
        } elseif ($sessionSecure === false) {
            $this->error('   ✗ SESSION_SECURE_COOKIE is false! Set to true for Railway HTTPS.');
        }

        if ($sessionDomain !== null) {
            $this->warn('   ⚠ SESSION_DOMAIN is set. Should be null for Railway.');
        }
        $this->newLine();

        // 6. Check database connection
        $this->info('6. Checking database connection...');
        try {
            DB::connection()->getPdo();
            $this->line('   ✓ Database connection working');
        } catch (\Exception $e) {
            $this->error('   ✗ Database connection failed: ' . $e->getMessage());
            return 1;
        }
        $this->newLine();

        // 7. Test session storage
        $this->info('7. Testing session storage...');
        try {
            session()->put('test_key', 'test_value');
            $value = session()->get('test_key');
            if ($value === 'test_value') {
                $this->line('   ✓ Session storage working');
                session()->forget('test_key');
            } else {
                $this->error('   ✗ Session storage not working correctly');
            }
        } catch (\Exception $e) {
            $this->error('   ✗ Session storage error: ' . $e->getMessage());
        }
        $this->newLine();

        // Summary
        $this->info('📋 Summary:');
        $this->line("   User: {$user->email}");
        $this->line("   Roles: " . implode(', ', $user->roles->pluck('name')->toArray()));
        $this->line("   Can Access Panel: " . ($user->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')) ? 'YES' : 'NO'));
        $this->line("   Session Driver: {$sessionDriver}");
        $this->newLine();

        $this->info('✅ Diagnosis complete!');
        $this->newLine();
        $this->line('If login still fails:');
        $this->line('1. Clear all caches: php artisan config:clear && php artisan cache:clear');
        $this->line('2. Check Railway logs: railway logs');
        $this->line('3. Verify APP_URL matches your Railway domain exactly');
        $this->line('4. Check browser console for errors');

        return 0;
    }
}

