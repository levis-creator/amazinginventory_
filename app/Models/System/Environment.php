<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Environment extends Model
{
    use SoftDeletes;

    protected $connection = 'system';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'is_default',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($environment) {
            if (empty($environment->slug)) {
                $environment->slug = Str::slug($environment->name);
            }
        });

        static::updating(function ($environment) {
            if ($environment->isDirty('name') && empty($environment->slug)) {
                $environment->slug = Str::slug($environment->name);
            }
        });

        // Ensure only one default environment
        static::saving(function ($environment) {
            if ($environment->is_default) {
                static::where('id', '!=', $environment->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * Get environment variables
     */
    public function variables(): HasMany
    {
        return $this->hasMany(EnvironmentVariable::class);
    }

    /**
     * Get active variables
     */
    public function activeVariables(): HasMany
    {
        return $this->variables()->whereNull('deleted_at');
    }

    /**
     * Scope for active environments
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default environment
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}

