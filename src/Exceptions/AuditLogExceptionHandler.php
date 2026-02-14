<?php

namespace Campelo\AuditLog\Exceptions;

use Campelo\AuditLog\AuditLogService;
use Illuminate\Http\Request;
use Throwable;

/**
 * Trait to integrate audit logging with Laravel's Exception Handler.
 *
 * Usage in App\Exceptions\Handler:
 *
 * use Campelo\AuditLog\Exceptions\AuditLogExceptionHandler;
 *
 * class Handler extends ExceptionHandler
 * {
 *     use AuditLogExceptionHandler;
 *
 *     public function register(): void
 *     {
 *         $this->reportable(function (Throwable $e) {
 *             $this->auditLogException($e);
 *         });
 *     }
 * }
 */
trait AuditLogExceptionHandler
{
    /**
     * Log an exception to the audit log.
     */
    protected function auditLogException(Throwable $exception, ?Request $request = null, ?array $context = null): void
    {
        if (!config('audit-log.errors.enabled', true)) {
            return;
        }

        try {
            app(AuditLogService::class)->logError($exception, $request, $context);
        } catch (Throwable $e) {
            // Silently fail to avoid infinite loops
            // Optionally log to Laravel's default logger
            if (config('app.debug')) {
                logger()->warning('AuditLog: Failed to log exception', [
                    'original_exception' => get_class($exception),
                    'audit_error' => $e->getMessage(),
                ]);
            }
        }
    }
}
