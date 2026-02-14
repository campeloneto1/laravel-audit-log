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
        // Capture start time and memory for performance tracking
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // Process the request
        $response = $next($request);

        // Calculate duration and memory usage
        $durationMs = (microtime(true) - $startTime) * 1000;
        $memoryUsage = memory_get_usage() - $startMemory;
        $peakMemory = memory_get_peak_usage();

        // Log slow request if threshold exceeded
        if ($this->shouldLogSlowRequest($request)) {
            $this->auditLogService->logSlowRequest(
                $request,
                $response,
                $durationMs,
                $memoryUsage,
                $peakMemory
            );
        }

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

    protected function shouldLogSlowRequest(Request $request): bool
    {
        if (!config('audit-log.performance.enabled', false)) {
            return false;
        }

        if (!config('audit-log.performance.slow_requests.enabled', true)) {
            return false;
        }

        // Check excluded routes
        $excludedRoutes = config('audit-log.excluded_routes', []);
        $currentPath = $request->path();

        foreach ($excludedRoutes as $pattern) {
            if ($this->matchesPattern($currentPath, $pattern)) {
                return false;
            }
        }

        return true;
    }
}
