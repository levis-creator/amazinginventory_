<?php

namespace App\Services;

use App\Models\System\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Audit Log Service
 *
 * Provides centralized audit logging functionality for tracking system changes.
 * Automatically masks sensitive fields (passwords, tokens, etc.) and captures
 * user information, IP addresses, and user agents.
 *
 * @package App\Services
 */
class AuditLogService
{
    /**
     * Log a general action.
     *
     * Creates an audit log entry for any action with optional model reference
     * and value changes. Automatically masks sensitive fields.
     *
     * @param string $action The action being logged (e.g., 'create', 'update', 'delete')
     * @param Model|null $model The model instance related to the action
     * @param array|null $oldValues Previous values before change
     * @param array|null $newValues New values after change
     * @param string|null $description Optional description of the action
     * @return AuditLog The created audit log entry
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
     * Log a model creation action.
     *
     * Convenience method for logging when a model is created.
     *
     * @param Model $model The created model instance
     * @param array $data The data used to create the model
     * @return AuditLog The created audit log entry
     */
    public function logCreate(Model $model, array $data): AuditLog
    {
        return $this->log('create', $model, null, $data, "Created {$this->getModelName($model)}");
    }

    /**
     * Log a model update action.
     *
     * Convenience method for logging when a model is updated.
     * Automatically identifies which fields changed.
     *
     * @param Model $model The updated model instance
     * @param array $oldData The data before the update
     * @param array $newData The data after the update
     * @return AuditLog The created audit log entry
     */
    public function logUpdate(Model $model, array $oldData, array $newData): AuditLog
    {
        $changes = $this->getChanges($oldData, $newData);
        $description = "Updated {$this->getModelName($model)}: " . implode(', ', array_keys($changes));

        return $this->log('update', $model, $oldData, $newData, $description);
    }

    /**
     * Log a model deletion action.
     *
     * Convenience method for logging when a model is deleted.
     *
     * @param Model $model The deleted model instance
     * @param array $data The data of the deleted model
     * @return AuditLog The created audit log entry
     */
    public function logDelete(Model $model, array $data): AuditLog
    {
        return $this->log('delete', $model, $data, null, "Deleted {$this->getModelName($model)}");
    }

    /**
     * Log a database connection test.
     *
     * Logs the result of testing a database connection configuration.
     *
     * @param string $connectionName The name of the connection being tested
     * @param bool $success Whether the connection test was successful
     * @param string|null $message Optional message about the test result
     * @return AuditLog The created audit log entry
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
     * Mask sensitive fields in data arrays.
     *
     * Replaces sensitive field values (passwords, tokens, etc.) with '***MASKED***'
     * to prevent them from being stored in audit logs.
     *
     * @param array $data The data array to mask
     * @return array The data array with sensitive fields masked
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
     * Get the differences between old and new data.
     *
     * Compares two data arrays and returns only the fields that changed,
     * along with their old and new values.
     *
     * @param array $oldData The data before changes
     * @param array $newData The data after changes
     * @return array Array of changed fields with 'old' and 'new' values
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
     * Get a human-readable model name for logging.
     *
     * Returns the model's class basename and ID for identification in logs.
     *
     * @param Model $model The model instance
     * @return string Formatted model name (e.g., "Product (ID: 123)")
     */
    protected function getModelName(Model $model): string
    {
        return class_basename($model) . " (ID: {$model->id})";
    }
}

