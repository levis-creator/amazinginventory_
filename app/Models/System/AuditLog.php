<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $connection = 'system';

    protected $fillable = [
        'action',
        'model_type',
        'model_id',
        'user_id',
        'user_email',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'description',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Get the model that this audit log belongs to
     */
    public function model()
    {
        return $this->morphTo();
    }

    /**
     * Scope for filtering by action
     */
    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for filtering by model
     */
    public function scopeForModel($query, string $modelType, ?int $modelId = null)
    {
        $query->where('model_type', $modelType);
        
        if ($modelId !== null) {
            $query->where('model_id', $modelId);
        }
        
        return $query;
    }

    /**
     * Scope for filtering by user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Mask sensitive fields in values
     */
    public function getMaskedValues(array $values): array
    {
        $sensitiveFields = ['password', 'password_confirmation', 'secret', 'api_key', 'token'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($values[$field])) {
                $values[$field] = '***MASKED***';
            }
        }
        
        return $values;
    }
}

