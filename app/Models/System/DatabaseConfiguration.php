<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class DatabaseConfiguration extends Model
{
    use SoftDeletes;

    protected $connection = 'system';

    protected $fillable = [
        'name',
        'driver',
        'host',
        'port',
        'database',
        'username',
        'password',
        'charset',
        'collation',
        'sslmode',
        'options',
        'is_default',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'options' => 'array',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Encrypt password before saving
     */
    public function setPasswordAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['password'] = Crypt::encryptString($value);
        }
    }

    /**
     * Decrypt password when accessing
     */
    public function getPasswordAttribute($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get connection array for Laravel config
     */
    public function toConnectionArray(): array
    {
        $config = [
            'driver' => $this->driver,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'charset' => $this->charset ?? 'utf8mb4',
            'prefix' => '',
            'prefix_indexes' => true,
        ];

        // Driver-specific configurations
        if ($this->driver === 'pgsql') {
            $config['sslmode'] = $this->sslmode ?? 'prefer';
            $config['search_path'] = 'public';
            // Enable prepared statement emulation for connection poolers (Supabase, etc.)
            $config['options'] = array_merge($config['options'] ?? [], [
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_PERSISTENT => false,
            ]);
        }

        if ($this->driver === 'mysql' || $this->driver === 'mariadb') {
            $config['collation'] = $this->collation ?? 'utf8mb4_unicode_ci';
            $config['strict'] = true;
        }

        if ($this->driver === 'sqlite') {
            $config['database'] = $this->database ?? database_path('database.sqlite');
            $config['foreign_key_constraints'] = true;
        }

        // Merge additional options
        if (!empty($this->options) && is_array($this->options)) {
            $config = array_merge($config, $this->options);
        }

        return $config;
    }

    /**
     * Scope for active configurations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default configuration
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}

