<?php

namespace Campelo\AuditLog\Middleware;

use Campelo\AuditLog\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Process the request
        $response = $next($request);

        // Log the request after it's processed
        if ($this->shouldLog($request)) {
            $this->auditLogService->logRequest($request, $response);
        }

        return $response;
    }

    protected function shouldLog(Request $request): bool
    {
        if (!config('audit-log.enabled', true)) {
            return false;
        }

        // Check if method should be logged
        $allowedMethods = config('audit-log.log_methods', ['POST', 'PUT', 'PATCH', 'DELETE']);
        if (!in_array($request->method(), $allowedMethods)) {
            return false;
        }

        // Check if route is excluded
        $excludedRoutes = config('audit-log.excluded_routes', []);
        $currentPath = $request->path();

        foreach ($excludedRoutes as $pattern) {
            if ($this->matchesPattern($currentPath, $pattern)) {
                return false;
            }
        }

        return true;
    }

    protected function matchesPattern(string $path, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\*', '.*', $pattern);

        return (bool) preg_match('/^' . $pattern . '$/', $path);
    }
}
