<?php

namespace Campelo\AuditLog\Http\Controllers;

use Campelo\AuditLog\Http\Resources\AuditLogResource;
use Campelo\AuditLog\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class AuditLogController extends Controller
{
    /**
     * List audit logs with filters.
     *
     * Filters:
     * - user_id: Filter by user ID
     * - event: Filter by event type (created, updated, deleted, etc.)
     * - events: Filter by multiple events (comma-separated)
     * - table: Filter by table name
     * - model: Filter by model type (full class name)
     * - model_id: Filter by model ID (requires model filter)
     * - method: Filter by HTTP method (GET, POST, PUT, DELETE)
     * - ip: Filter by IP address
     * - route: Filter by route name
     * - date_from: Filter from date (Y-m-d or Y-m-d H:i:s)
     * - date_to: Filter to date (Y-m-d or Y-m-d H:i:s)
     * - search: Search in description, url, user_name, user_email
     * - per_page: Items per page (default 15, max 100)
     * - sort: Sort field (default: performed_at)
     * - order: Sort order (asc/desc, default: desc)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AuditLog::query();

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Filter by event type
        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        // Filter by multiple events
        if ($request->filled('events')) {
            $events = explode(',', $request->input('events'));
            $query->whereIn('event', $events);
        }

        // Filter by table name
        if ($request->filled('table')) {
            $query->where('table_name', $request->input('table'));
        }

        // Filter by model type
        if ($request->filled('model')) {
            $query->where('auditable_type', $request->input('model'));

            // Filter by model ID (only if model is specified)
            if ($request->filled('model_id')) {
                $query->where('auditable_id', $request->input('model_id'));
            }
        }

        // Filter by HTTP method
        if ($request->filled('method')) {
            $query->where('method', strtoupper($request->input('method')));
        }

        // Filter by IP address
        if ($request->filled('ip')) {
            $query->where('ip_address', $request->input('ip'));
        }

        // Filter by route name
        if ($request->filled('route')) {
            $query->where('route_name', $request->input('route'));
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('performed_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('performed_at', '<=', $request->input('date_to'));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('user_email', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortField = $request->input('sort', 'performed_at');
        $sortOrder = $request->input('order', 'desc');

        $allowedSorts = ['performed_at', 'event', 'table_name', 'user_id', 'method', 'created_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        // Pagination
        $perPage = min($request->input('per_page', 15), 100);

        return AuditLogResource::collection($query->paginate($perPage));
    }

    /**
     * Show a specific audit log entry.
     */
    public function show(int $id): AuditLogResource|JsonResponse
    {
        $auditLog = AuditLog::find($id);

        if (!$auditLog) {
            return response()->json(['error' => 'Audit log not found'], 404);
        }

        return new AuditLogResource($auditLog);
    }

    /**
     * Get audit logs for a specific model.
     */
    public function forModel(Request $request, string $model, int $id): AnonymousResourceCollection
    {
        $query = AuditLog::where('auditable_type', $model)
            ->where('auditable_id', $id);

        // Apply event filter if provided
        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        $query->orderByDesc('performed_at');

        $perPage = min($request->input('per_page', 15), 100);

        return AuditLogResource::collection($query->paginate($perPage));
    }

    /**
     * Get audit logs for a specific user.
     */
    public function forUser(Request $request, int $userId): AnonymousResourceCollection
    {
        $query = AuditLog::where('user_id', $userId);

        // Apply date filters
        if ($request->filled('date_from')) {
            $query->where('performed_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('performed_at', '<=', $request->input('date_to'));
        }

        $query->orderByDesc('performed_at');

        $perPage = min($request->input('per_page', 15), 100);

        return AuditLogResource::collection($query->paginate($perPage));
    }

    /**
     * Get statistics/summary of audit logs.
     */
    public function stats(Request $request): JsonResponse
    {
        $query = AuditLog::query();

        // Apply date filters
        if ($request->filled('date_from')) {
            $query->where('performed_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('performed_at', '<=', $request->input('date_to'));
        }

        $stats = [
            'total' => (clone $query)->count(),
            'by_event' => (clone $query)
                ->selectRaw('event, count(*) as count')
                ->groupBy('event')
                ->pluck('count', 'event'),
            'by_table' => (clone $query)
                ->whereNotNull('table_name')
                ->selectRaw('table_name, count(*) as count')
                ->groupBy('table_name')
                ->pluck('count', 'table_name'),
            'by_method' => (clone $query)
                ->whereNotNull('method')
                ->selectRaw('method, count(*) as count')
                ->groupBy('method')
                ->pluck('count', 'method'),
            'by_user' => (clone $query)
                ->whereNotNull('user_id')
                ->selectRaw('user_id, user_name, count(*) as count')
                ->groupBy('user_id', 'user_name')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(fn ($item) => [
                    'user_id' => $item->user_id,
                    'user_name' => $item->user_name,
                    'count' => $item->count,
                ]),
            'recent_activity' => (clone $query)
                ->selectRaw('DATE(performed_at) as date, count(*) as count')
                ->groupBy('date')
                ->orderByDesc('date')
                ->limit(30)
                ->pluck('count', 'date'),
        ];

        return response()->json($stats);
    }

    /**
     * Get available filter options.
     */
    public function filters(): JsonResponse
    {
        return response()->json([
            'events' => AuditLog::distinct()->pluck('event')->filter()->values(),
            'tables' => AuditLog::distinct()->whereNotNull('table_name')->pluck('table_name')->values(),
            'methods' => AuditLog::distinct()->whereNotNull('method')->pluck('method')->values(),
            'users' => AuditLog::whereNotNull('user_id')
                ->selectRaw('DISTINCT user_id, user_name, user_email')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->user_id,
                    'name' => $item->user_name,
                    'email' => $item->user_email,
                ]),
        ]);
    }

    /**
     * Delete old audit logs (cleanup).
     */
    public function cleanup(Request $request): JsonResponse
    {
        $days = $request->input('days', config('audit-log.retention_days', 365));

        if ($days === null) {
            return response()->json(['error' => 'Retention is set to forever'], 400);
        }

        $cutoffDate = now()->subDays($days);

        $deleted = AuditLog::where('performed_at', '<', $cutoffDate)->delete();

        return response()->json([
            'deleted' => $deleted,
            'message' => "Deleted {$deleted} audit logs older than {$days} days",
        ]);
    }
}
