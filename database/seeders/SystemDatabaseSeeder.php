<?php

namespace Database\Seeders;

use App\Models\System\SystemAdmin;
use App\Models\System\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SystemDatabaseSeeder extends Seeder
{
    /**
     * Seed the system database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding system database...');

        // Seed system settings
        $this->seedSystemSettings();

        // Seed system admin (for emergency access)
        $this->seedSystemAdmin();

        $this->command->info('✅ System database seeded successfully!');
    }

    /**
     * Seed system settings
     */
    protected function seedSystemSettings(): void
    {
        $this->command->info('  Creating system settings...');

        $settings = [
            [
                'key' => 'app_name',
                'value' => env('APP_NAME', 'Laravel'),
                'type' => 'string',
                'description' => 'Application name',
            ],
            [
                'key' => 'database_config_enabled',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Enable database configuration management',
            ],
            [
                'key' => 'audit_log_retention_days',
                'value' => '90',
                'type' => 'integer',
                'description' => 'Number of days to retain audit logs',
            ],
        ];

        foreach ($settings as $setting) {
            SystemSetting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
            $this->command->line("    ✓ Setting: {$setting['key']}");
        }
    }

    /**
     * Seed system admin for emergency access
     */
    protected function seedSystemAdmin(): void
    {
        $this->command->info('  Creating system admin...');

        $systemAdminEmail = env('SYSTEM_ADMIN_EMAIL', env('FILAMENT_ADMIN_EMAIL', 'system@amazinginventory.com'));
        $systemAdminPassword = env('SYSTEM_ADMIN_PASSWORD', env('FILAMENT_ADMIN_PASSWORD', 'SecurePassword123!'));
        $systemAdminName = env('SYSTEM_ADMIN_NAME', 'System Administrator');

        $systemAdmin = SystemAdmin::firstOrCreate(
            ['email' => $systemAdminEmail],
            [
                'name' => $systemAdminName,
                'password' => Hash::make($systemAdminPassword),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        // Update password if user exists
        if (!$systemAdmin->wasRecentlyCreated) {
            $systemAdmin->password = Hash::make($systemAdminPassword);
            $systemAdmin->name = $systemAdminName;
            $systemAdmin->is_active = true;
            $systemAdmin->save();
        }

        $this->command->line("    ✓ System admin: {$systemAdmin->email}");
        $this->command->info("    Note: System admin is for emergency access when app database is unavailable.");
    }
}

