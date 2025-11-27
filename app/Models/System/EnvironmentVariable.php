<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class EnvironmentVariable extends Model
{
    use SoftDeletes;

    protected $connection = 'system';

    protected $fillable = [
        'environment_id',
        'key',
        'value',
        'type',
        'description',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Get the environment that owns this variable
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Get typed value
     */
    public function getTypedValue()
    {
        $value = $this->value;

        // Decrypt if encrypted
        if ($this->is_encrypted && !empty($value)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Exception $e) {
                \Log::warning("Failed to decrypt environment variable {$this->key}: " . $e->getMessage());
                return null;
            }
        }

        return match ($this->type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Set typed value
     */
    public function setTypedValue($value): void
    {
        $formattedValue = match ($this->type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };

        // Encrypt if needed
        if ($this->is_encrypted && !empty($formattedValue)) {
            $this->value = Crypt::encryptString($formattedValue);
        } else {
            $this->value = $formattedValue;
        }
    }

    /**
     * Encrypt value before saving
     */
    public function setValueAttribute($value)
    {
        if ($this->is_encrypted && !empty($value)) {
            try {
                // Try to decrypt first - if it fails, it's not encrypted yet
                Crypt::decryptString($value);
                // Already encrypted, store as is
                $this->attributes['value'] = $value;
            } catch (\Exception $e) {
                // Not encrypted, encrypt it
                $this->attributes['value'] = Crypt::encryptString($value);
            }
        } else {
            $this->attributes['value'] = $value;
        }
    }
}

