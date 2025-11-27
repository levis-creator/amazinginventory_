<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $connection = 'system';

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    protected $casts = [
        'value' => 'string',
    ];

    /**
     * Get setting value with type casting
     */
    public function getTypedValue()
    {
        $value = $this->value;

        return match ($this->type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Set setting value with type conversion
     */
    public function setTypedValue($value)
    {
        $this->value = match ($this->type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };
    }

    /**
     * Get setting by key
     */
    public static function getValue(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }

        return $setting->getTypedValue();
    }

    /**
     * Set setting by key
     */
    public static function setValue(string $key, $value, string $type = 'string', ?string $description = null): void
    {
        $setting = static::firstOrNew(['key' => $key]);
        $setting->type = $type;
        $setting->description = $description;
        $setting->setTypedValue($value);
        $setting->save();
    }
}

