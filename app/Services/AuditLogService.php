<?php

namespace App\Services;

use App\Models\System\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Log an action
     */
    public function log(
        string $action,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null
    ): AuditLog {
        $user = Auth::user();

        return AuditLog::create([
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->id,
            'user_id' => $user?->id,
            'user_email' => $user?->email ?? 'system',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $oldValues ? $this->maskSensitiveFields($oldValues) : null,
            'new_values' => $newValues ? $this->maskSensitiveFields($newValues) : null,
            'description' => $description,
        ]);
    }

    /**
     * Log configuration creation
     */
    public function logCreate(Model $model, array $data): AuditLog
    {
        return $this->log('create', $model, null, $data, "Created {$this->getModelName($model)}");
    }

    /**
     * Log configuration update
     */
    public function logUpdate(Model $model, array $oldData, array $newData): AuditLog
    {
        $changes = $this->getChanges($oldData, $newData);
        $description = "Updated {$this->getModelName($model)}: " . implode(', ', array_keys($changes));

        return $this->log('update', $model, $oldData, $newData, $description);
    }

    /**
     * Log configuration deletion
     */
    public function logDelete(Model $model, array $data): AuditLog
    {
        return $this->log('delete', $model, $data, null, "Deleted {$this->getModelName($model)}");
    }

    /**
     * Log connection test
     */
    public function logConnectionTest(string $connectionName, bool $success, ?string $message = null): AuditLog
    {
        return $this->log(
            'test_connection',
            null,
            null,
            [
                'connection' => $connectionName,
                'success' => $success,
                'message' => $message,
            ],
            $success 
                ? "Tested connection '{$connectionName}' successfully"
                : "Failed to test connection '{$connectionName}': {$message}"
        );
    }

    /**
     * Mask sensitive fields
     */
    protected function maskSensitiveFields(array $data): array
    {
        $sensitiveFields = ['password', 'password_confirmation', 'secret', 'api_key', 'token'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***MASKED***';
            }
        }
        
        return $data;
    }

    /**
     * Get changes between old and new data
     */
    protected function getChanges(array $oldData, array $newData): array
    {
        $changes = [];
        
        foreach ($newData as $key => $value) {
            if (!isset($oldData[$key]) || $oldData[$key] !== $value) {
                $changes[$key] = [
                    'old' => $oldData[$key] ?? null,
                    'new' => $value,
                ];
            }
        }
        
        return $changes;
    }

    /**
     * Get model name for logging
     */
    protected function getModelName(Model $model): string
    {
        return class_basename($model) . " (ID: {$model->id})";
    }
}

