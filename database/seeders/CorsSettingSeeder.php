<?php

namespace Database\Seeders;

use App\Models\CorsSetting;
use Illuminate\Database\Seeder;

class CorsSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if there's already an active setting
        if (CorsSetting::where('is_active', true)->exists()) {
            return;
        }

        // Get default origins from env or use defaults
        $defaultOrigins = explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:5173,http://127.0.0.1:3000,http://127.0.0.1:5173'));

        CorsSetting::create([
            'paths' => ['api/*', 'sanctum/csrf-cookie'],
            'allowed_methods' => ['*'],
            'allowed_origins' => array_filter(array_map('trim', $defaultOrigins)),
            'allowed_origins_patterns' => [],
            'allowed_headers' => ['*'],
            'exposed_headers' => [],
            'max_age' => 0,
            'supports_credentials' => true,
            'is_active' => true,
        ]);
    }
}

