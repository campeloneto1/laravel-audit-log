<?php

namespace Campelo\AuditLog;

use Campelo\AuditLog\Jobs\WriteAuditLog;
use Campelo\AuditLog\Models\AuditLog;
use Campelo\AuditLog\Notifications\AuditLogErrorNotification;
use Campelo\AuditLog\Notifications\AuditLogNotifiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

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

    /**
     * Log an error/exception.
     */
    public function logError(
        Throwable $exception,
        ?Request $request = null,
        ?array $context = null
    ): ?AuditLog {
        if (!$this->shouldLogError($exception)) {
            return null;
        }

        $request = $request ?? request();
        $statusCode = $this->getExceptionStatusCode($exception);

        $metadata = [
            'exception_class' => get_class($exception),
            'exception_code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'context' => $context ? $this->filterSensitiveData($context) : null,
        ];

        if (config('audit-log.errors.log_stack_trace', true)) {
            $metadata['stack_trace'] = $this->formatStackTrace($exception);
        }

        $data = [
            'user_id' => $this->getUserId(),
            'user_type' => $this->getUserType(),
            'user_name' => $this->getUserName(),
            'user_email' => $this->getUserEmail(),
            'performed_at' => now(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'url' => $request?->fullUrl(),
            'method' => $request?->method(),
            'route_name' => $request?->route()?->getName(),
            'event' => 'error',
            'response_code' => $statusCode,
            'request_data' => $request ? $this->filterSensitiveData($request->all()) : null,
            'metadata' => $metadata,
            'description' => $this->buildErrorDescription($exception, $statusCode),
        ];

        $result = $this->write($data);

        // Send notification if enabled
        $this->sendErrorNotification($data);

        return $result;
    }

    /**
     * Send error notification if enabled.
     */
    protected function sendErrorNotification(array $data): void
    {
        if (!config('audit-log.notifications.enabled', false)) {
            return;
        }

        // Check if response code should trigger notification
        $notifyOnCodes = config('audit-log.notifications.notify_on_codes', [500, 501, 502, 503, 504]);
        if (!empty($notifyOnCodes) && !in_array($data['response_code'], $notifyOnCodes)) {
            return;
        }

        // Check throttling
        if ($this->isNotificationThrottled($data)) {
            return;
        }

        try {
            $notifiable = new AuditLogNotifiable();
            $notifiable->notify(new AuditLogErrorNotification($data));
        } catch (Throwable $e) {
            // Silently fail to avoid breaking the app
            if (config('app.debug')) {
                logger()->warning('AuditLog: Failed to send notification', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Check if notification is throttled.
     */
    protected function isNotificationThrottled(array $data): bool
    {
        if (!config('audit-log.notifications.throttle.enabled', true)) {
            return false;
        }

        $exceptionClass = $data['metadata']['exception_class'] ?? 'unknown';
        $file = $data['metadata']['file'] ?? '';
        $line = $data['metadata']['line'] ?? '';

        $cacheKey = 'audit_log_notify_' . md5($exceptionClass . $file . $line);

        $maxNotifications = config('audit-log.notifications.throttle.max_notifications', 5);
        $decayMinutes = config('audit-log.notifications.throttle.decay_minutes', 60);

        $count = Cache::get($cacheKey, 0);

        if ($count >= $maxNotifications) {
            return true;
        }

        Cache::put($cacheKey, $count + 1, now()->addMinutes($decayMinutes));

        return false;
    }

    /**
     * Check if the exception should be logged.
     */
    protected function shouldLogError(Throwable $exception): bool
    {
        if (!config('audit-log.errors.enabled', true)) {
            return false;
        }

        // Check if exception class is excluded
        $excludedExceptions = config('audit-log.errors.excluded_exceptions', []);
        foreach ($excludedExceptions as $excludedException) {
            if ($exception instanceof $excludedException) {
                return false;
            }
        }

        // Check error family (4xx or 5xx)
        $statusCode = $this->getExceptionStatusCode($exception);

        $is4xx = $statusCode >= 400 && $statusCode < 500;
        $is5xx = $statusCode >= 500 && $statusCode < 600;

        if ($is4xx && !config('audit-log.errors.log_4xx', false)) {
            return false;
        }

        if ($is5xx && !config('audit-log.errors.log_5xx', true)) {
            return false;
        }

        // If it's not 4xx or 5xx, log it as a generic error (5xx behavior)
        if (!$is4xx && !$is5xx) {
            return config('audit-log.errors.log_5xx', true);
        }

        return true;
    }

    /**
     * Get the HTTP status code from an exception.
     */
    protected function getExceptionStatusCode(Throwable $exception): int
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        if (method_exists($exception, 'getStatusCode')) {
            return $exception->getStatusCode();
        }

        // Default to 500 for generic exceptions
        return 500;
    }

    /**
     * Format the stack trace for logging.
     */
    protected function formatStackTrace(Throwable $exception): string
    {
        $maxLength = config('audit-log.errors.max_stack_trace_length', 5000);
        $trace = $exception->getTraceAsString();

        if (strlen($trace) > $maxLength) {
            return substr($trace, 0, $maxLength) . "\n...[truncated]";
        }

        return $trace;
    }

    /**
     * Build a description for the error.
     */
    protected function buildErrorDescription(Throwable $exception, int $statusCode): string
    {
        $class = class_basename($exception);
        $message = $exception->getMessage();

        if (strlen($message) > 200) {
            $message = substr($message, 0, 200) . '...';
        }

        return "[{$statusCode}] {$class}: {$message}";
    }
}
