<?php

namespace Campelo\AuditLog;

use Campelo\AuditLog\Exceptions\RollbackException;
use Campelo\AuditLog\Jobs\WriteAuditLog;
use Campelo\AuditLog\Models\AuditLog;
use Campelo\AuditLog\Notifications\AuditLogErrorNotification;
use Campelo\AuditLog\Notifications\AuditLogNotifiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    // =========================================================================
    // PERFORMANCE LOGGING
    // =========================================================================

    /**
     * Log a slow database query.
     */
    public function logSlowQuery(
        string $sql,
        float $timeMs,
        array $bindings = [],
        ?string $connectionName = null
    ): ?AuditLog {
        if (!$this->shouldLogSlowQuery($sql, $timeMs)) {
            return null;
        }

        $metadata = [
            'query' => $sql,
            'execution_time_ms' => round($timeMs, 2),
            'connection' => $connectionName,
        ];

        if (config('audit-log.performance.slow_queries.log_bindings', true)) {
            $metadata['bindings'] = $this->filterSensitiveData($bindings);
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
            'event' => 'slow_query',
            'metadata' => $metadata,
            'description' => $this->buildSlowQueryDescription($sql, $timeMs),
        ];

        return $this->write($data);
    }

    /**
     * Log a slow HTTP request.
     */
    public function logSlowRequest(
        Request $request,
        Response $response,
        float $durationMs,
        ?int $memoryUsage = null,
        ?int $peakMemoryUsage = null
    ): ?AuditLog {
        if (!$this->shouldLogSlowRequest($durationMs)) {
            return null;
        }

        $metadata = [
            'duration_ms' => round($durationMs, 2),
        ];

        if (config('audit-log.performance.slow_requests.log_memory', true)) {
            if ($memoryUsage !== null) {
                $metadata['memory_usage_bytes'] = $memoryUsage;
                $metadata['memory_usage_mb'] = round($memoryUsage / 1024 / 1024, 2);
            }
            if ($peakMemoryUsage !== null) {
                $metadata['peak_memory_bytes'] = $peakMemoryUsage;
                $metadata['peak_memory_mb'] = round($peakMemoryUsage / 1024 / 1024, 2);
            }
        }

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
            'event' => 'slow_request',
            'response_code' => $response->getStatusCode(),
            'metadata' => $metadata,
            'description' => $this->buildSlowRequestDescription($request, $durationMs),
        ];

        return $this->write($data);
    }

    /**
     * Check if slow query should be logged.
     */
    protected function shouldLogSlowQuery(string $sql, float $timeMs): bool
    {
        if (!config('audit-log.performance.enabled', false)) {
            return false;
        }

        if (!config('audit-log.performance.slow_queries.enabled', true)) {
            return false;
        }

        $threshold = config('audit-log.performance.slow_queries.threshold', 1000);
        if ($timeMs < $threshold) {
            return false;
        }

        // Check excluded patterns
        $excludedPatterns = config('audit-log.performance.slow_queries.excluded_patterns', []);
        foreach ($excludedPatterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if slow request should be logged.
     */
    protected function shouldLogSlowRequest(float $durationMs): bool
    {
        if (!config('audit-log.performance.enabled', false)) {
            return false;
        }

        if (!config('audit-log.performance.slow_requests.enabled', true)) {
            return false;
        }

        $threshold = config('audit-log.performance.slow_requests.threshold', 2000);

        return $durationMs >= $threshold;
    }

    /**
     * Build description for slow query log.
     */
    protected function buildSlowQueryDescription(string $sql, float $timeMs): string
    {
        $truncatedSql = strlen($sql) > 100 ? substr($sql, 0, 100) . '...' : $sql;

        return sprintf('Slow query (%.2f ms): %s', $timeMs, $truncatedSql);
    }

    /**
     * Build description for slow request log.
     */
    protected function buildSlowRequestDescription(Request $request, float $durationMs): string
    {
        return sprintf(
            'Slow request (%.2f ms): %s %s',
            $durationMs,
            $request->method(),
            $request->path()
        );
    }

    // =========================================================================
    // ROLLBACK
    // =========================================================================

    /**
     * Rollback a model to a previous state based on audit log ID.
     *
     * @throws RollbackException
     */
    public function rollback(int $auditLogId): ?AuditLog
    {
        if (!config('audit-log.rollback.enabled', true)) {
            throw RollbackException::disabled();
        }

        if (!$this->isRollbackAuthorized()) {
            throw RollbackException::notAuthorized();
        }

        $auditLog = AuditLog::find($auditLogId);

        if (!$auditLog) {
            throw RollbackException::auditLogNotFound($auditLogId);
        }

        return $this->performRollback($auditLog);
    }

    /**
     * Rollback a chain of changes (multiple undos).
     *
     * @param int $auditLogId Starting audit log ID
     * @param int $steps Number of steps to rollback (default 1)
     * @return array Array of rollback audit logs created
     * @throws RollbackException
     */
    public function rollbackChain(int $auditLogId, int $steps = 1): array
    {
        if (!config('audit-log.rollback.enabled', true)) {
            throw RollbackException::disabled();
        }

        if (!$this->isRollbackAuthorized()) {
            throw RollbackException::notAuthorized();
        }

        $maxChain = config('audit-log.rollback.max_chain_length', 10);

        if ($maxChain > 0 && $steps > $maxChain) {
            throw RollbackException::chainLimitExceeded($maxChain);
        }

        $auditLog = AuditLog::find($auditLogId);

        if (!$auditLog) {
            throw RollbackException::auditLogNotFound($auditLogId);
        }

        if (!$auditLog->auditable_type || !$auditLog->auditable_id) {
            throw RollbackException::noModelReference();
        }

        // Get the chain of audit logs to rollback
        $chain = AuditLog::where('auditable_type', $auditLog->auditable_type)
            ->where('auditable_id', $auditLog->auditable_id)
            ->where('performed_at', '<=', $auditLog->performed_at)
            ->whereIn('event', config('audit-log.rollback.rollbackable_events', ['created', 'updated', 'deleted']))
            ->orderByDesc('performed_at')
            ->limit($steps)
            ->get();

        $rollbacks = [];

        DB::transaction(function () use ($chain, &$rollbacks) {
            foreach ($chain as $log) {
                $rollback = $this->performRollback($log);
                if ($rollback) {
                    $rollbacks[] = $rollback;
                }
            }
        });

        return $rollbacks;
    }

    /**
     * Check if an audit log can be rolled back.
     */
    public function canRollback(int $auditLogId): array
    {
        if (!config('audit-log.rollback.enabled', true)) {
            return ['can_rollback' => false, 'reason' => 'Rollback feature is disabled.'];
        }

        if (!$this->isRollbackAuthorized()) {
            return ['can_rollback' => false, 'reason' => 'User is not authorized to perform rollback.'];
        }

        $auditLog = AuditLog::find($auditLogId);

        if (!$auditLog) {
            return ['can_rollback' => false, 'reason' => 'Audit log not found.'];
        }

        try {
            $this->validateRollback($auditLog);
            return ['can_rollback' => true, 'reason' => null];
        } catch (RollbackException $e) {
            return ['can_rollback' => false, 'reason' => $e->getMessage()];
        }
    }

    /**
     * Check if current user is authorized to perform rollback.
     */
    protected function isRollbackAuthorized(): bool
    {
        $allowedUsers = config('audit-log.rollback.allowed_users', []);

        // If list is empty, nobody can rollback
        if (empty($allowedUsers)) {
            return false;
        }

        $currentUserId = Auth::id();

        if ($currentUserId === null) {
            return false;
        }

        return in_array($currentUserId, $allowedUsers);
    }

    /**
     * Perform the actual rollback operation.
     *
     * @throws RollbackException
     */
    protected function performRollback(AuditLog $auditLog): ?AuditLog
    {
        $this->validateRollback($auditLog);

        $modelClass = $auditLog->auditable_type;
        $modelId = $auditLog->auditable_id;
        $event = $auditLog->event;
        $oldValues = $auditLog->old_values;

        $model = null;
        $restoredValues = [];
        $previousValues = [];

        DB::transaction(function () use (
            $modelClass, $modelId, $event, $oldValues,
            &$model, &$restoredValues, &$previousValues
        ) {
            switch ($event) {
                case 'created':
                    // Rollback creation = delete the model
                    $model = $modelClass::find($modelId);
                    if ($model) {
                        $previousValues = $model->getAttributes();
                        $model->delete();
                        $restoredValues = null; // Model deleted
                    }
                    break;

                case 'updated':
                    // Rollback update = restore old values
                    $model = $modelClass::find($modelId);
                    if (!$model) {
                        throw RollbackException::modelNotFound($modelClass, $modelId);
                    }
                    $previousValues = [];
                    foreach (array_keys($oldValues) as $key) {
                        $previousValues[$key] = $model->getAttribute($key);
                    }
                    $model->fill($oldValues);
                    $model->saveQuietly(); // Don't trigger audit events
                    $restoredValues = $oldValues;
                    break;

                case 'deleted':
                    // Rollback deletion = restore the model
                    // Try to find soft-deleted model first
                    if (method_exists($modelClass, 'withTrashed')) {
                        $model = $modelClass::withTrashed()->find($modelId);

                        if ($model && method_exists($model, 'trashed') && $model->trashed()) {
                            // Restore soft-deleted model
                            $model->restore();
                            $restoredValues = $model->getAttributes();
                            break;
                        }
                    }

                    // Recreate the model with old values
                    if ($oldValues) {
                        $model = new $modelClass();
                        $model->fill($oldValues);
                        if (isset($oldValues['id'])) {
                            $model->{$model->getKeyName()} = $oldValues['id'];
                        }
                        $model->saveQuietly();
                        $restoredValues = $oldValues;
                    } else {
                        throw RollbackException::noOldValues($auditLog->id);
                    }
                    break;
            }
        });

        // Log the rollback event
        if (config('audit-log.rollback.log_rollback', true)) {
            return $this->logRollback($auditLog, $model, $previousValues, $restoredValues);
        }

        return null;
    }

    /**
     * Validate that rollback is possible.
     *
     * @throws RollbackException
     */
    protected function validateRollback(AuditLog $auditLog): void
    {
        $rollbackableEvents = config('audit-log.rollback.rollbackable_events', ['created', 'updated', 'deleted']);

        if (!in_array($auditLog->event, $rollbackableEvents)) {
            throw RollbackException::eventNotRollbackable($auditLog->event);
        }

        if (!$auditLog->auditable_type) {
            throw RollbackException::noModelReference();
        }

        if (!class_exists($auditLog->auditable_type)) {
            throw RollbackException::modelClassNotFound($auditLog->auditable_type);
        }

        if ($auditLog->event === 'updated' && empty($auditLog->old_values)) {
            throw RollbackException::noOldValues($auditLog->id);
        }

        if ($auditLog->event === 'deleted' && empty($auditLog->old_values)) {
            // Check if soft-delete model exists
            $modelClass = $auditLog->auditable_type;
            if (method_exists($modelClass, 'withTrashed')) {
                $model = $modelClass::withTrashed()->find($auditLog->auditable_id);
                if (!$model) {
                    throw RollbackException::noOldValues($auditLog->id);
                }
            } else {
                throw RollbackException::noOldValues($auditLog->id);
            }
        }

        // Check if already rolled back
        $existingRollback = AuditLog::where('event', 'rollback')
            ->whereRaw("JSON_EXTRACT(metadata, '$.rolled_back_audit_log_id') = ?", [$auditLog->id])
            ->exists();

        if ($existingRollback) {
            throw RollbackException::alreadyRolledBack($auditLog->id);
        }
    }

    /**
     * Log the rollback event.
     */
    protected function logRollback(
        AuditLog $originalLog,
        ?Model $model,
        ?array $previousValues,
        ?array $restoredValues
    ): ?AuditLog {
        $metadata = [
            'rolled_back_audit_log_id' => $originalLog->id,
            'rolled_back_event' => $originalLog->event,
            'rolled_back_at' => $originalLog->performed_at->toIso8601String(),
        ];

        $description = sprintf(
            'Rolled back %s on %s #%s (from audit log #%d)',
            $originalLog->event,
            class_basename($originalLog->auditable_type),
            $originalLog->auditable_id,
            $originalLog->id
        );

        return $this->log(
            event: 'rollback',
            model: $model,
            oldValues: $previousValues,
            newValues: $restoredValues,
            description: $description,
            metadata: $metadata
        );
    }
}
