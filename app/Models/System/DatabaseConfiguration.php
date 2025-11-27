<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use PDO;

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
        // SQLite has a different structure - no host, port, username, password
        if ($this->driver === 'sqlite') {
            $config = [
                'driver' => 'sqlite',
                'database' => $this->database ?? database_path('database.sqlite'),
                'prefix' => '',
                'prefix_indexes' => true,
                'foreign_key_constraints' => true,
                'busy_timeout' => null,
                'journal_mode' => null,
                'synchronous' => null,
                'transaction_mode' => 'DEFERRED',
            ];
        } else {
            // For other drivers (MySQL, PostgreSQL, etc.)
            $config = [
                'driver' => $this->driver,
                'host' => $this->host ?? '127.0.0.1',
                'port' => $this->port ?? ($this->driver === 'pgsql' ? '5432' : '3306'),
                'database' => $this->database ?? '',
                'username' => $this->username ?? '',
                'password' => $this->password ?? '',
                'charset' => $this->charset ?? ($this->driver === 'pgsql' ? 'utf8' : 'utf8mb4'),
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
                    // Note: For PostgreSQL, connection timeout is controlled by default_socket_timeout
                    // PDO::ATTR_TIMEOUT doesn't work for PostgreSQL connections
                ]);
            }

            if ($this->driver === 'mysql' || $this->driver === 'mariadb') {
                $config['collation'] = $this->collation ?? 'utf8mb4_unicode_ci';
                $config['strict'] = true;
            }
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

