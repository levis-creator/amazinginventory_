<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorsSetting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'paths',
        'allowed_methods',
        'allowed_origins',
        'allowed_origins_patterns',
        'allowed_headers',
        'exposed_headers',
        'max_age',
        'supports_credentials',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paths' => 'array',
            'allowed_methods' => 'array',
            'allowed_origins' => 'array',
            'allowed_origins_patterns' => 'array',
            'allowed_headers' => 'array',
            'exposed_headers' => 'array',
            'max_age' => 'integer',
            'supports_credentials' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the model's default attribute values.
     *
     * @return array<string, mixed>
     */
    protected function attributes(): array
    {
        return [
            'paths' => ['api/*', 'sanctum/csrf-cookie'],
            'allowed_methods' => ['*'],
            'allowed_origins_patterns' => [],
            'allowed_headers' => ['*'],
            'exposed_headers' => [],
            'max_age' => 0,
            'supports_credentials' => true,
            'is_active' => true,
        ];
    }

    /**
     * Get the active CORS settings
     */
    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }
}

