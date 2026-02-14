# Laravel Audit Log

Automatic audit logging for Laravel applications. Track who did what, when, where, and what changed.

## Features

- **Automatic Request Logging** - Middleware logs all HTTP requests (POST, PUT, PATCH, DELETE by default)
- **Model Event Logging** - Trait to automatically log model changes (created, updated, deleted)
- **Error Logging** - Capture and log exceptions and errors (4xx, 5xx) with stack traces
- **Performance Logging** - Track slow database queries and slow HTTP requests
- **Rollback** - Revert model changes based on audit log history
- **Detailed Information** - Captures user, IP, user agent, URL, method, table, old/new values
- **Built-in API** - Query audit logs with filters via REST API
- **Sensitive Data Protection** - Automatically redacts passwords and sensitive fields
- **Queue Support** - Offload logging to queues for better performance
- **Customizable** - Configure which methods, routes, events, and error types to log
- **Notifications** - Send email/Slack alerts when critical errors occur

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

### 4. Error Logging

Automatically log all exceptions and errors that occur in your application.

**Step 1: Configure in your `.env`:**

```env
AUDIT_LOG_ERRORS_ENABLED=true
AUDIT_LOG_ERRORS_4XX=false   # Log client errors (400-499)
AUDIT_LOG_ERRORS_5XX=true    # Log server errors (500-599)
```

**Step 2: Integrate with your Exception Handler:**

```php
// app/Exceptions/Handler.php

use Campelo\AuditLog\Exceptions\AuditLogExceptionHandler;

class Handler extends ExceptionHandler
{
    use AuditLogExceptionHandler;

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            $this->auditLogException($e);
        });
    }
}
```

**Manual error logging:**

```php
use Campelo\AuditLog\Facades\AuditLog;

try {
    // Some operation that might fail
    $this->processPayment($order);
} catch (PaymentException $e) {
    // Log the error with additional context
    AuditLog::logError($e, request(), [
        'order_id' => $order->id,
        'amount' => $order->total,
    ]);

    throw $e;
}
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
| `event` | Filter by event type | `?event=updated` or `?event=error` |
| `events` | Multiple events (comma-separated) | `?events=created,updated` |
| `table` | Filter by table name | `?table=users` |
| `model` | Filter by model class | `?model=App\Models\User` |
| `model_id` | Filter by model ID (requires model) | `?model=App\Models\User&model_id=1` |
| `method` | Filter by HTTP method | `?method=POST` |
| `ip` | Filter by IP address | `?ip=192.168.1.1` |
| `route` | Filter by route name | `?route=users.update` |
| `response_code` | Filter by HTTP response code | `?response_code=500` |
| `date_from` | Filter from date | `?date_from=2024-01-01` |
| `date_to` | Filter to date | `?date_to=2024-12-31` |
| `search` | Search in description, URL, user name/email | `?search=john` |
| `per_page` | Items per page (max 100) | `?per_page=50` |
| `sort` | Sort field | `?sort=performed_at` |

**Examples:**

```bash
# Get all error logs
GET /api/audit-logs?event=error

# Get only server errors (500)
GET /api/audit-logs?event=error&response_code=500

# Get errors from today
GET /api/audit-logs?event=error&date_from=2024-01-15

# Get all CRUD operations (exclude errors)
GET /api/audit-logs?events=created,updated,deleted
```
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

## Automatic Cleanup

The package includes a command to clean up old audit logs based on retention policy.

### Configuration

```env
# Global retention (days)
AUDIT_LOG_RETENTION_DAYS=365

# Error logs retention (if different from global)
AUDIT_LOG_ERRORS_RETENTION_DAYS=90

# Enable automatic cleanup
AUDIT_LOG_CLEANUP_ENABLED=true
AUDIT_LOG_CLEANUP_SCHEDULE=02:00
```

### Automatic Cleanup (Recommended)

Enable automatic cleanup in your `.env`:

```env
AUDIT_LOG_CLEANUP_ENABLED=true
AUDIT_LOG_CLEANUP_SCHEDULE=02:00
```

The package will automatically register the cleanup command in Laravel's scheduler. Make sure your server has cron configured:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### Manual Cleanup

Run the cleanup command manually:

```bash
# Clean all logs based on config retention
php artisan audit-log:cleanup

# Preview what would be deleted (dry run)
php artisan audit-log:cleanup --dry-run

# Override retention days
php artisan audit-log:cleanup --days=30

# Different retention for errors
php artisan audit-log:cleanup --days=365 --error-days=30

# Clean only error logs
php artisan audit-log:cleanup --type=errors

# Clean only operation logs (exclude errors)
php artisan audit-log:cleanup --type=operations
```

### Manual Scheduler Registration

If you prefer to register the command manually in your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Daily cleanup at 2 AM
    $schedule->command('audit-log:cleanup')->dailyAt('02:00');

    // Or weekly on Sunday
    $schedule->command('audit-log:cleanup')->weeklyOn(0, '03:00');

    // With custom retention
    $schedule->command('audit-log:cleanup --days=90 --error-days=30')->daily();
}
```

## Notifications

Get notified via Email and/or Slack when critical errors (5xx) occur.

### Configuration

```env
# Enable notifications
AUDIT_LOG_NOTIFICATIONS_ENABLED=true

# Choose channels: mail, slack, or both
AUDIT_LOG_NOTIFY_CHANNELS=mail          # Only email
AUDIT_LOG_NOTIFY_CHANNELS=slack         # Only Slack
AUDIT_LOG_NOTIFY_CHANNELS=mail,slack    # Both

# Email recipient (uses Laravel's mail config)
AUDIT_LOG_NOTIFY_EMAIL=admin@example.com

# Slack webhook URL
AUDIT_LOG_SLACK_WEBHOOK=https://hooks.slack.com/services/xxx/yyy/zzz
```

### Requirements

- **Email**: Uses Laravel's built-in mail configuration (`config/mail.php`)
- **Slack**: Requires the Slack notification channel package:

```bash
composer require laravel/slack-notification-channel
```

### Throttling

To prevent notification spam, the package throttles repeated errors:

- Same error (same exception class + file + line) will only notify 5 times per hour
- Configurable in `config/audit-log.php`:

```php
'notifications' => [
    'throttle' => [
        'enabled' => true,
        'max_notifications' => 5,   // Max notifications per error type
        'decay_minutes' => 60,      // Time window
    ],
],
```

### Which errors trigger notifications

By default, only server errors (5xx) trigger notifications. You can customize via `.env`:

```env
# Default: 500,501,502,503,504
AUDIT_LOG_NOTIFY_ON_CODES=500,502,503

# Include all server errors
AUDIT_LOG_NOTIFY_ON_CODES=500,501,502,503,504,505,506,507,508,510,511

# Include some client errors too
AUDIT_LOG_NOTIFY_ON_CODES=401,403,500,502,503
```

Or in config file:

```php
'notifications' => [
    'notify_on_codes' => [500, 501, 502, 503, 504],
],
```

## Performance Logging

Track slow database queries and slow HTTP requests for performance monitoring.

### Configuration

```env
# Enable performance logging
AUDIT_LOG_PERFORMANCE_ENABLED=true

# Slow query threshold (milliseconds)
AUDIT_LOG_SLOW_QUERY_THRESHOLD=1000

# Include query bindings in logs
AUDIT_LOG_SLOW_QUERY_BINDINGS=true

# Slow request threshold (milliseconds)
AUDIT_LOG_SLOW_REQUEST_THRESHOLD=2000
```

### Querying Performance Logs

```php
use Campelo\AuditLog\Models\AuditLog;

// Get all slow queries
$slowQueries = AuditLog::slowQueries()->get();

// Get all slow requests
$slowRequests = AuditLog::slowRequests()->get();

// Get all performance logs
$performance = AuditLog::performance()->get();

// Filter by duration (in metadata)
$verySlowQueries = AuditLog::slowQueries()
    ->whereRaw("JSON_EXTRACT(metadata, '$.execution_time_ms') > 5000")
    ->get();
```

### API Endpoints

```bash
# Get slow query logs
GET /api/audit-logs?event=slow_query

# Get slow request logs
GET /api/audit-logs?event=slow_request

# Get all performance logs
GET /api/audit-logs?events=slow_query,slow_request
```

### Response Format

**Slow Query:**
```json
{
    "id": 100,
    "event": "slow_query",
    "performed_at": "2024-01-15T10:30:00+00:00",
    "description": "Slow query (1523.45 ms): SELECT * FROM orders WHERE...",
    "metadata": {
        "query": "SELECT * FROM orders WHERE status = ? AND created_at > ?",
        "execution_time_ms": 1523.45,
        "connection": "mysql",
        "bindings": ["pending", "2024-01-01"]
    }
}
```

**Slow Request:**
```json
{
    "id": 101,
    "event": "slow_request",
    "performed_at": "2024-01-15T10:31:00+00:00",
    "description": "Slow request (3245.67 ms): GET /api/reports/sales",
    "metadata": {
        "duration_ms": 3245.67,
        "memory_usage_bytes": 52428800,
        "memory_usage_mb": 50.0,
        "peak_memory_bytes": 67108864,
        "peak_memory_mb": 64.0
    }
}
```

## Rollback

Revert model changes based on audit log history. Only authorized users can perform rollback.

### Configuration

```env
# Enable rollback feature
AUDIT_LOG_ROLLBACK_ENABLED=true

# User IDs authorized to perform rollback (comma-separated)
AUDIT_LOG_ROLLBACK_ALLOWED_USERS=1,5,10

# Maximum chain length for rollback (0 = unlimited)
AUDIT_LOG_ROLLBACK_MAX_CHAIN=10
```

### Basic Rollback

```php
use Campelo\AuditLog\Facades\AuditLog;
use Campelo\AuditLog\Models\AuditLog as AuditLogModel;

// Rollback by audit log ID
$rollbackLog = AuditLog::rollback($auditLogId);

// Check if rollback is possible
$result = AuditLog::canRollback($auditLogId);
// Returns: ['can_rollback' => true/false, 'reason' => '...']

// Rollback via model instance
$auditLog = AuditLogModel::find($id);
$rollbackLog = $auditLog->rollback();
```

### Rollback from Model

```php
// Rollback to a specific audit log
$user->rollbackTo($auditLogId);

// Rollback to previous state
$user->rollbackToPrevious();
```

### Rollback Chain (Multiple Undos)

```php
// Rollback the last 3 changes
$rollbackLogs = AuditLog::rollbackChain($auditLogId, steps: 3);
```

### API Endpoints

```bash
# Check if rollback is possible
GET /api/audit-logs/{id}/can-rollback

# Response
{
    "can_rollback": true,
    "reason": null
}

# Perform rollback
POST /api/audit-logs/{id}/rollback

# Response
{
    "success": true,
    "message": "Rollback completed successfully.",
    "rollback_log": { ... }
}

# Rollback chain
POST /api/audit-logs/rollback-chain/{id}
Body: { "steps": 3 }

# Response
{
    "success": true,
    "message": "Rolled back 3 changes.",
    "rollback_logs": [...]
}
```

### Rollback Events

Each rollback creates a new audit log with event `rollback`:

```json
{
    "id": 150,
    "event": "rollback",
    "description": "Rolled back updated on User #1 (from audit log #42)",
    "old_values": { "name": "New Name" },
    "new_values": { "name": "Original Name" },
    "metadata": {
        "rolled_back_audit_log_id": 42,
        "rolled_back_event": "updated",
        "rolled_back_at": "2024-01-15T10:00:00+00:00"
    }
}
```

### Querying Rollbacks

```php
// Get all rollback events
$rollbacks = AuditLog::rollbacks()->get();

// Get rollbackable events only
$rollbackable = AuditLog::rollbackable()->get();

// Check if an audit log was rolled back
$auditLog = AuditLogModel::find($id);
if ($auditLog->isRolledBack()) {
    $rollbackLog = $auditLog->getRollbackLog();
}
```

### Limitations

- **created**: Rollback deletes the model
- **updated**: Rollback restores old values
- **deleted**: Rollback restores soft-deleted models or recreates with old_values
- Cannot rollback events without `old_values` (e.g., if not stored)
- Cannot rollback non-model events (e.g., `error`, `slow_query`)
- Each audit log can only be rolled back once

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

## Query Examples

### Normal Operation Logs (CRUD)

```php
use Campelo\AuditLog\Models\AuditLog;

// All create operations
$created = AuditLog::event('created')->latest('performed_at')->get();

// All update operations for a specific table
$userUpdates = AuditLog::event('updated')
    ->forTable('users')
    ->get();

// All delete operations by a specific user
$deletedByAdmin = AuditLog::event('deleted')
    ->byUser($adminId)
    ->get();

// All write operations (POST, PUT, PATCH, DELETE) today
$todayWrites = AuditLog::writeOperations()
    ->whereDate('performed_at', today())
    ->get();

// All read operations (GET) - if enabled in config
$reads = AuditLog::readOperations()->get();

// History of a specific record
$orderHistory = AuditLog::forModel(Order::class, $orderId)
    ->oldest('performed_at')
    ->get();

// Activity by user in a date range
$userActivity = AuditLog::byUser($userId)
    ->between('2024-01-01', '2024-01-31')
    ->get();
```

### Error Logs

```php
use Campelo\AuditLog\Models\AuditLog;

// All errors
$allErrors = AuditLog::errors()->latest('performed_at')->get();

// Only server errors (500-599)
$serverErrors = AuditLog::serverErrors()->get();

// Only client errors (400-499)
$clientErrors = AuditLog::clientErrors()->get();

// Errors by response code
$notFound = AuditLog::errors()->responseCode(404)->get();
$forbidden = AuditLog::errors()->responseCode(403)->get();

// Errors in a specific route
$apiErrors = AuditLog::errors()
    ->where('url', 'like', '%/api/payments%')
    ->get();

// Errors by a specific user
$userErrors = AuditLog::errors()
    ->byUser($userId)
    ->get();

// Recent errors (last 24 hours)
$recentErrors = AuditLog::errors()
    ->where('performed_at', '>=', now()->subDay())
    ->get();

// Errors with specific exception class
$paymentErrors = AuditLog::errors()
    ->whereJsonContains('metadata->exception_class', 'App\\Exceptions\\PaymentException')
    ->get();

// Error statistics by day
$errorsByDay = AuditLog::errors()
    ->selectRaw('DATE(performed_at) as date, COUNT(*) as count')
    ->groupBy('date')
    ->orderBy('date', 'desc')
    ->get();

// Top error types
$topErrors = AuditLog::errors()
    ->selectRaw("JSON_EXTRACT(metadata, '$.exception_class') as exception, COUNT(*) as count")
    ->groupBy('exception')
    ->orderBy('count', 'desc')
    ->limit(10)
    ->get();
```

### Combined Queries

```php
// All activity (normal + errors) by a user
$allActivity = AuditLog::byUser($userId)
    ->latest('performed_at')
    ->get();

// Separate normal logs from errors
$normalLogs = AuditLog::where('event', '!=', 'error')->get();
$errorLogs = AuditLog::errors()->get();

// Dashboard statistics
$stats = [
    'total_operations' => AuditLog::where('event', '!=', 'error')->count(),
    'total_errors' => AuditLog::errors()->count(),
    'server_errors' => AuditLog::serverErrors()->count(),
    'client_errors' => AuditLog::clientErrors()->count(),
    'creates_today' => AuditLog::event('created')->whereDate('performed_at', today())->count(),
    'updates_today' => AuditLog::event('updated')->whereDate('performed_at', today())->count(),
    'deletes_today' => AuditLog::event('deleted')->whereDate('performed_at', today())->count(),
    'errors_today' => AuditLog::errors()->whereDate('performed_at', today())->count(),
];
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

    // Automatic cleanup
    'cleanup' => [
        'enabled' => env('AUDIT_LOG_CLEANUP_ENABLED', false),
        'schedule' => env('AUDIT_LOG_CLEANUP_SCHEDULE', '02:00'),
    ],

    // Error logging configuration
    'errors' => [
        'enabled' => env('AUDIT_LOG_ERRORS_ENABLED', true),
        'log_4xx' => env('AUDIT_LOG_ERRORS_4XX', false),
        'log_5xx' => env('AUDIT_LOG_ERRORS_5XX', true),
        'log_stack_trace' => true,
        'max_stack_trace_length' => 5000,
        'excluded_exceptions' => [
            \Illuminate\Auth\AuthenticationException::class,
            \Illuminate\Validation\ValidationException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        ],
        'retention_days' => env('AUDIT_LOG_ERRORS_RETENTION_DAYS', null),
    ],

    // Notifications
    'notifications' => [
        'enabled' => env('AUDIT_LOG_NOTIFICATIONS_ENABLED', false),
        'channels' => explode(',', env('AUDIT_LOG_NOTIFY_CHANNELS', 'mail')),
        'mail' => [
            'to' => env('AUDIT_LOG_NOTIFY_EMAIL', null),
        ],
        'slack' => [
            'webhook_url' => env('AUDIT_LOG_SLACK_WEBHOOK', null),
        ],
        'throttle' => [
            'enabled' => true,
            'max_notifications' => 5,
            'decay_minutes' => 60,
        ],
        // Configurable via AUDIT_LOG_NOTIFY_ON_CODES=500,502,503
        'notify_on_codes' => env('AUDIT_LOG_NOTIFY_ON_CODES')
            ? array_map('intval', explode(',', env('AUDIT_LOG_NOTIFY_ON_CODES')))
            : [500, 501, 502, 503, 504],
    ],

    // Performance logging
    'performance' => [
        'enabled' => env('AUDIT_LOG_PERFORMANCE_ENABLED', false),
        'slow_queries' => [
            'enabled' => env('AUDIT_LOG_SLOW_QUERIES_ENABLED', true),
            'threshold' => env('AUDIT_LOG_SLOW_QUERY_THRESHOLD', 1000),
            'log_bindings' => env('AUDIT_LOG_SLOW_QUERY_BINDINGS', true),
        ],
        'slow_requests' => [
            'enabled' => env('AUDIT_LOG_SLOW_REQUESTS_ENABLED', true),
            'threshold' => env('AUDIT_LOG_SLOW_REQUEST_THRESHOLD', 2000),
            'log_memory' => true,
        ],
    ],

    // Rollback
    'rollback' => [
        'enabled' => env('AUDIT_LOG_ROLLBACK_ENABLED', true),
        // Configurable via AUDIT_LOG_ROLLBACK_ALLOWED_USERS=1,5,10
        'allowed_users' => env('AUDIT_LOG_ROLLBACK_ALLOWED_USERS')
            ? array_map('intval', explode(',', env('AUDIT_LOG_ROLLBACK_ALLOWED_USERS')))
            : [],
        'rollbackable_events' => ['created', 'updated', 'deleted'],
        'max_chain_length' => env('AUDIT_LOG_ROLLBACK_MAX_CHAIN', 10),
        'log_rollback' => true,
    ],
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

### Normal Operation Log

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

### Error Log

```json
{
    "id": 42,
    "user": {
        "id": 1,
        "type": "App\\Models\\User",
        "name": "John Doe",
        "email": "john@example.com"
    },
    "performed_at": "2024-01-15T14:22:00+00:00",
    "performed_at_human": "5 minutes ago",
    "request": {
        "ip": "192.168.1.1",
        "user_agent": "Mozilla/5.0...",
        "url": "https://example.com/api/orders/process",
        "method": "POST",
        "route": "orders.process"
    },
    "event": "error",
    "response_code": 500,
    "description": "[500] PaymentException: Payment gateway timeout",
    "metadata": {
        "exception_class": "App\\Exceptions\\PaymentException",
        "exception_code": 0,
        "file": "/var/www/app/Services/PaymentService.php",
        "line": 142,
        "stack_trace": "#0 /var/www/app/Http/Controllers/OrderController.php(85): App\\Services\\PaymentService->process()...",
        "context": {
            "order_id": 123,
            "amount": 99.99
        }
    },
    "summary": "Error by John Doe"
}
```

## License

MIT
