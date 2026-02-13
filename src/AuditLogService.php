<?php

namespace Campelo\AuditLog;

use Campelo\AuditLog\Jobs\WriteAuditLog;
use Campelo\AuditLog\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuditLogService
{
    /**
     * Log a request/response.
     */
    public function logRequest(Request $request, Response $response): ?AuditLog
    {
        $data = [
            'user_id' => $this->getUserId(),
            'user_type' => $this->getUserType(),
            'user_name' => $this->getUserName(),
            'user_email' => $this->getUserEmail(),
            'performed_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'route_name' => $request->route()?->getName(),
            'event' => $this->getEventFromMethod($request->method()),
            'request_data' => $this->filterSensitiveData($request->all()),
            'response_code' => $response->getStatusCode(),
            'description' => $this->buildRequestDescription($request),
        ];

        return $this->write($data);
    }

    /**
     * Log a model event.
     */
    public function logModelEvent(Model $model, string $event): ?AuditLog
    {
        // Check if model wants to be audited
        if (method_exists($model, 'shouldBeAudited') && !$model->shouldBeAudited()) {
            return null;
        }

        $oldValues = null;
        $newValues = null;
        $changedFields = null;

        if ($event === 'created') {
            $newValues = $this->getAuditableAttributes($model);
        } elseif ($event === 'updated') {
            $oldValues = $this->getOriginalValues($model);
            $newValues = $this->getAuditableAttributes($model);
            $changedFields = array_keys($model->getDirty());
        } elseif ($event === 'deleted') {
            $oldValues = $this->getAuditableAttributes($model);
        } elseif ($event === 'restored') {
            $newValues = $this->getAuditableAttributes($model);
        }

        $description = null;
        if (method_exists($model, 'getAuditDescription')) {
            $description = $model->getAuditDescription($event);
        }

        $metadata = [];
        if (method_exists($model, 'getAuditCustomData')) {
            $metadata = $model->getAuditCustomData();
        }

        $data = [
            'user_id' => $this->getUserId(),
            'user_type' => $this->getUserType(),
            'user_name' => $this->getUserName(),
            'user_email' => $this->getUserEmail(),
            'performed_at' => now(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'url' => request()?->fullUrl(),
            'method' => request()?->method(),
            'route_name' => request()?->route()?->getName(),
            'event' => $event,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'table_name' => $model->getTable(),
            'old_values' => $oldValues ? $this->filterSensitiveData($oldValues) : null,
            'new_values' => $newValues ? $this->filterSensitiveData($newValues) : null,
            'changed_fields' => $changedFields,
            'metadata' => $metadata ?: null,
            'description' => $description,
        ];

        return $this->write($data);
    }

    /**
     * Log a custom event.
     */
    public function log(
        string $event,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?array $metadata = null
    ): ?AuditLog {
        $data = [
            'user_id' => $this->getUserId(),
            'user_type' => $this->getUserType(),
            'user_name' => $this->getUserName(),
            'user_email' => $this->getUserEmail(),
            'performed_at' => now(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'url' => request()?->fullUrl(),
            'method' => request()?->method(),
            'route_name' => request()?->route()?->getName(),
            'event' => $event,
            'auditable_type' => $model ? get_class($model) : null,
            'auditable_id' => $model?->getKey(),
            'table_name' => $model?->getTable(),
            'old_values' => $oldValues ? $this->filterSensitiveData($oldValues) : null,
            'new_values' => $newValues ? $this->filterSensitiveData($newValues) : null,
            'metadata' => $metadata,
            'description' => $description,
        ];

        return $this->write($data);
    }

    /**
     * Write the audit log entry.
     */
    protected function write(array $data): ?AuditLog
    {
        if (!config('audit-log.enabled', true)) {
            return null;
        }

        // Use queue if enabled
        if (config('audit-log.queue.enabled', false)) {
            WriteAuditLog::dispatch($data)
                ->onConnection(config('audit-log.queue.connection', 'default'))
                ->onQueue(config('audit-log.queue.queue', 'audit-logs'));

            return null;
        }

        return AuditLog::create($data);
    }

    /**
     * Get the current user ID.
     */
    protected function getUserId(): ?int
    {
        $resolver = config('audit-log.user_resolver');

        if ($resolver && class_exists($resolver)) {
            return app($resolver)->resolve();
        }

        return Auth::id();
    }

    /**
     * Get the current user type (for polymorphic relations).
     */
    protected function getUserType(): ?string
    {
        $user = Auth::user();

        return $user ? get_class($user) : null;
    }

    /**
     * Get the current user name.
     */
    protected function getUserName(): ?string
    {
        $user = Auth::user();

        return $user?->name ?? $user?->username ?? null;
    }

    /**
     * Get the current user email.
     */
    protected function getUserEmail(): ?string
    {
        return Auth::user()?->email;
    }

    /**
     * Get the event name from HTTP method.
     */
    protected function getEventFromMethod(string $method): string
    {
        return match (strtoupper($method)) {
            'POST' => 'created',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            default => 'accessed',
        };
    }

    /**
     * Filter sensitive data from the payload.
     */
    protected function filterSensitiveData(array $data): array
    {
        $excludedFields = config('audit-log.excluded_fields', []);
        $maxLength = config('audit-log.max_field_length', 500);

        $filtered = [];

        foreach ($data as $key => $value) {
            // Check if field is sensitive
            if (in_array(strtolower($key), array_map('strtolower', $excludedFields))) {
                $filtered[$key] = '[REDACTED]';
                continue;
            }

            // Handle nested arrays
            if (is_array($value)) {
                $filtered[$key] = $this->filterSensitiveData($value);
                continue;
            }

            // Truncate long strings
            if (is_string($value) && strlen($value) > $maxLength) {
                $filtered[$key] = substr($value, 0, $maxLength) . '...[truncated]';
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }

    /**
     * Get the auditable attributes from a model.
     */
    protected function getAuditableAttributes(Model $model): array
    {
        $attributes = $model->getAttributes();

        // Check for include list
        if (method_exists($model, 'getAuditInclude')) {
            $include = $model->getAuditInclude();
            if (!empty($include)) {
                $attributes = array_intersect_key($attributes, array_flip($include));
            }
        }

        // Check for exclude list
        if (method_exists($model, 'getAuditExclude')) {
            $exclude = $model->getAuditExclude();
            $attributes = array_diff_key($attributes, array_flip($exclude));
        }

        return $attributes;
    }

    /**
     * Get the original values before update.
     */
    protected function getOriginalValues(Model $model): array
    {
        $dirty = $model->getDirty();
        $original = [];

        foreach (array_keys($dirty) as $key) {
            $original[$key] = $model->getOriginal($key);
        }

        return $original;
    }

    /**
     * Build a description for the request.
     */
    protected function buildRequestDescription(Request $request): string
    {
        $method = $request->method();
        $path = $request->path();
        $routeName = $request->route()?->getName();

        if ($routeName) {
            return "{$method} {$routeName} ({$path})";
        }

        return "{$method} {$path}";
    }
}
