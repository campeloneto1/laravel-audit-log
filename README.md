# Laravel Audit Log

Automatic audit logging for Laravel applications. Track who did what, when, where, and what changed.

## Features

- **Automatic Request Logging** - Middleware logs all HTTP requests (POST, PUT, PATCH, DELETE by default)
- **Model Event Logging** - Trait to automatically log model changes (created, updated, deleted)
- **Detailed Information** - Captures user, IP, user agent, URL, method, table, old/new values
- **Built-in API** - Query audit logs with filters via REST API
- **Sensitive Data Protection** - Automatically redacts passwords and sensitive fields
- **Queue Support** - Offload logging to queues for better performance
- **Customizable** - Configure which methods, routes, events to log

## Installation

```bash
composer require campelo/laravel-audit-log
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=audit-log-config
php artisan vendor:publish --tag=audit-log-migrations
php artisan migrate
```

## Quick Start

### 1. Automatic Request Logging

The middleware is automatically registered. All POST, PUT, PATCH, DELETE requests will be logged.

```php
// config/audit-log.php
'log_methods' => [
    'POST',
    'PUT',
    'PATCH',
    'DELETE',
    // 'GET', // Uncomment to also log read operations
],
```

### 2. Model Event Logging

Add the `Auditable` trait to your models:

```php
use Campelo\AuditLog\Traits\Auditable;

class User extends Model
{
    use Auditable;

    // Optional: exclude sensitive fields
    protected array $auditExclude = ['password', 'remember_token'];

    // Optional: only include specific fields
    protected array $auditInclude = ['name', 'email', 'role'];
}
```

### 3. Manual Logging

```php
use Campelo\AuditLog\Facades\AuditLog;

// Log a custom event
AuditLog::log(
    event: 'user_promoted',
    model: $user,
    oldValues: ['role' => 'user'],
    newValues: ['role' => 'admin'],
    description: 'User was promoted to admin'
);
```

## API Endpoints

The package provides built-in API endpoints to query audit logs:

### List Audit Logs

```
GET /api/audit-logs
```

**Query Parameters:**

| Parameter | Description | Example |
|-----------|-------------|---------|
| `user_id` | Filter by user ID | `?user_id=1` |
| `event` | Filter by event type | `?event=updated` |
| `events` | Multiple events (comma-separated) | `?events=created,updated` |
| `table` | Filter by table name | `?table=users` |
| `model` | Filter by model class | `?model=App\Models\User` |
| `model_id` | Filter by model ID (requires model) | `?model=App\Models\User&model_id=1` |
| `method` | Filter by HTTP method | `?method=POST` |
| `ip` | Filter by IP address | `?ip=192.168.1.1` |
| `route` | Filter by route name | `?route=users.update` |
| `date_from` | Filter from date | `?date_from=2024-01-01` |
| `date_to` | Filter to date | `?date_to=2024-12-31` |
| `search` | Search in description, URL, user name/email | `?search=john` |
| `per_page` | Items per page (max 100) | `?per_page=50` |
| `sort` | Sort field | `?sort=performed_at` |
| `order` | Sort order (asc/desc) | `?order=desc` |

### Get Single Entry

```
GET /api/audit-logs/{id}
```

### Get Logs for Model

```
GET /api/audit-logs/model/{model}/{id}
GET /api/audit-logs/model/App%5CModels%5CUser/1
```

### Get Logs for User

```
GET /api/audit-logs/user/{userId}
```

### Get Statistics

```
GET /api/audit-logs/stats
GET /api/audit-logs/stats?date_from=2024-01-01&date_to=2024-01-31
```

Returns:
- Total count
- Count by event type
- Count by table
- Count by HTTP method
- Top 10 users by activity
- Daily activity for last 30 days

### Get Filter Options

```
GET /api/audit-logs/filters
```

Returns available values for events, tables, methods, and users.

### Cleanup Old Logs

```
DELETE /api/audit-logs/cleanup?days=365
```

## Query Using Model

```php
use Campelo\AuditLog\Models\AuditLog;

// Get all logs for a model
$logs = AuditLog::forModel($user)->get();

// Get logs for a specific user
$logs = AuditLog::byUser($user)->get();

// Get logs for a specific event
$logs = AuditLog::event('updated')->get();

// Get logs for a table
$logs = AuditLog::forTable('users')->get();

// Get logs between dates
$logs = AuditLog::between('2024-01-01', '2024-01-31')->get();

// Get logs from IP
$logs = AuditLog::fromIp('192.168.1.1')->get();

// Get only write operations
$logs = AuditLog::writeOperations()->get();

// Combine scopes
$logs = AuditLog::byUser($user)
    ->event(['created', 'updated'])
    ->between($startDate, $endDate)
    ->get();
```

## Accessing Audit Logs from Models

```php
// Get all audit logs
$user->auditLogs;

// Get last audit log
$user->lastAuditLog();

// Get logs for specific event
$user->getAuditLogsForEvent('updated');
```

## Configuration

```php
// config/audit-log.php

return [
    // Enable/disable globally
    'enabled' => env('AUDIT_LOG_ENABLED', true),

    // Database connection (null = default)
    'connection' => null,

    // Table name
    'table' => 'audit_logs',

    // HTTP methods to log
    'log_methods' => ['POST', 'PUT', 'PATCH', 'DELETE'],

    // Model events to log
    'log_events' => ['created', 'updated', 'deleted', 'restored'],

    // Routes to exclude
    'excluded_routes' => [
        'telescope/*',
        'horizon/*',
        '_debugbar/*',
    ],

    // Fields to redact
    'excluded_fields' => [
        'password',
        'password_confirmation',
        'secret',
        'token',
        'api_key',
    ],

    // Queue configuration
    'queue' => [
        'enabled' => env('AUDIT_LOG_QUEUE', false),
        'connection' => 'default',
        'queue' => 'audit-logs',
    ],

    // Data retention (days, null = forever)
    'retention_days' => 365,

    // API routes
    'routes_enabled' => true,
    'route_prefix' => 'api/audit-logs',
    'route_middleware' => ['api', 'auth'],
];
```

## Customization

### Custom User Resolver

```php
// config/audit-log.php
'user_resolver' => App\Services\CustomUserResolver::class,

// App/Services/CustomUserResolver.php
class CustomUserResolver
{
    public function resolve(): ?int
    {
        return auth('admin')->id() ?? auth()->id();
    }
}
```

### Custom Audit Data

```php
class Order extends Model
{
    use Auditable;

    public function getAuditCustomData(): array
    {
        return [
            'total' => $this->total,
            'items_count' => $this->items->count(),
        ];
    }

    public function getAuditDescription(string $event): ?string
    {
        return "Order #{$this->id} was {$event}";
    }

    public function shouldBeAudited(): bool
    {
        // Don't audit draft orders
        return $this->status !== 'draft';
    }
}
```

## Response Format

```json
{
    "id": 1,
    "user": {
        "id": 1,
        "type": "App\\Models\\User",
        "name": "John Doe",
        "email": "john@example.com"
    },
    "performed_at": "2024-01-15T10:30:00+00:00",
    "performed_at_human": "2 hours ago",
    "request": {
        "ip": "192.168.1.1",
        "user_agent": "Mozilla/5.0...",
        "url": "https://example.com/api/users/1",
        "method": "PUT",
        "route": "users.update"
    },
    "event": "updated",
    "model": {
        "type": "App\\Models\\User",
        "id": 1,
        "table": "users"
    },
    "changes": {
        "old": { "name": "John" },
        "new": { "name": "John Doe" },
        "fields": ["name"],
        "diff": {
            "name": { "old": "John", "new": "John Doe" }
        }
    },
    "summary": "Updated User #1 by John Doe"
}
```

## License

MIT
