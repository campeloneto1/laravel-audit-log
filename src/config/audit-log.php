<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Audit Log
    |--------------------------------------------------------------------------
    |
    | Enable or disable the audit log globally.
    |
    */
    'enabled' => env('AUDIT_LOG_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The database connection to use for storing audit logs.
    | Leave null to use the default connection.
    |
    */
    'connection' => env('AUDIT_LOG_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Table Name
    |--------------------------------------------------------------------------
    |
    | The name of the table to store audit logs.
    |
    */
    'table' => 'audit_logs',

    /*
    |--------------------------------------------------------------------------
    | HTTP Methods to Log
    |--------------------------------------------------------------------------
    |
    | Which HTTP methods should be logged.
    | Options: GET, POST, PUT, PATCH, DELETE
    |
    */
    'log_methods' => [
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        // 'GET', // Uncomment to log read operations
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Register Middleware
    |--------------------------------------------------------------------------
    |
    | Automatically register the audit log middleware for web and api routes.
    | If false, you need to manually add 'audit-log' middleware to your routes.
    |
    */
    'auto_register_middleware' => true,

    /*
    |--------------------------------------------------------------------------
    | Log Model Events
    |--------------------------------------------------------------------------
    |
    | Which model events should be logged when using the Auditable trait.
    |
    */
    'log_events' => [
        'created',
        'updated',
        'deleted',
        'restored',
        // 'retrieved', // Uncomment to log read operations
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Routes
    |--------------------------------------------------------------------------
    |
    | Routes that should not be logged.
    | Supports wildcards: 'admin/*', 'api/health'
    |
    */
    'excluded_routes' => [
        'telescope/*',
        'horizon/*',
        '_debugbar/*',
        'sanctum/*',
        'livewire/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Fields
    |--------------------------------------------------------------------------
    |
    | Fields that should never be logged (sensitive data).
    | These will be replaced with '[REDACTED]'.
    |
    */
    'excluded_fields' => [
        'password',
        'password_confirmation',
        'current_password',
        'secret',
        'token',
        'api_key',
        'credit_card',
        'cvv',
    ],

    /*
    |--------------------------------------------------------------------------
    | User Resolver
    |--------------------------------------------------------------------------
    |
    | How to resolve the current user.
    | Default uses Auth::id(). You can specify a custom class.
    |
    */
    'user_resolver' => null, // e.g., App\Services\CustomUserResolver::class

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model class for the relationship.
    |
    */
    'user_model' => 'App\\Models\\User',

    /*
    |--------------------------------------------------------------------------
    | Log Request Body
    |--------------------------------------------------------------------------
    |
    | Whether to log the request body/payload.
    |
    */
    'log_request_body' => true,

    /*
    |--------------------------------------------------------------------------
    | Log Response
    |--------------------------------------------------------------------------
    |
    | Whether to log the response body.
    | Warning: This can significantly increase storage usage.
    |
    */
    'log_response' => false,

    /*
    |--------------------------------------------------------------------------
    | Max Field Length
    |--------------------------------------------------------------------------
    |
    | Maximum length for field values in the log.
    | Longer values will be truncated.
    |
    */
    'max_field_length' => 500,

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Queue the audit log writes for better performance.
    |
    */
    'queue' => [
        'enabled' => env('AUDIT_LOG_QUEUE', false),
        'connection' => env('AUDIT_LOG_QUEUE_CONNECTION', 'default'),
        'queue' => env('AUDIT_LOG_QUEUE_NAME', 'audit-logs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | How long to keep audit logs (in days).
    | Set to null to keep forever.
    |
    */
    'retention_days' => env('AUDIT_LOG_RETENTION_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Automatic Cleanup
    |--------------------------------------------------------------------------
    |
    | Automatically schedule cleanup of old audit logs.
    | The cleanup runs daily at the specified time.
    |
    */
    'cleanup' => [
        'enabled' => env('AUDIT_LOG_CLEANUP_ENABLED', false),
        'schedule' => env('AUDIT_LOG_CLEANUP_SCHEDULE', '02:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Console Commands
    |--------------------------------------------------------------------------
    |
    | Whether to log artisan console commands.
    |
    */
    'log_console' => false,

    /*
    |--------------------------------------------------------------------------
    | Console Commands to Log
    |--------------------------------------------------------------------------
    |
    | Which console commands should be logged (if log_console is true).
    | Use '*' to log all commands.
    |
    */
    'console_commands' => [
        'migrate',
        'migrate:fresh',
        'migrate:rollback',
        'db:seed',
        'cache:clear',
        'config:clear',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Routes
    |--------------------------------------------------------------------------
    |
    | Enable or disable the built-in API routes for querying audit logs.
    |
    */
    'routes_enabled' => env('AUDIT_LOG_ROUTES_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | The prefix for the audit log API routes.
    |
    */
    'route_prefix' => env('AUDIT_LOG_ROUTE_PREFIX', 'api/audit-logs'),

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware to apply to the audit log API routes.
    | You should include authentication middleware here.
    |
    */
    'route_middleware' => ['api', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Error Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for logging exceptions and errors.
    | Integrate with your App\Exceptions\Handler using the AuditLogExceptionHandler trait.
    |
    */
    'errors' => [
        // Enable or disable error logging
        'enabled' => env('AUDIT_LOG_ERRORS_ENABLED', true),

        // Log client errors (400-499)
        'log_4xx' => env('AUDIT_LOG_ERRORS_4XX', false),

        // Log server errors (500-599)
        'log_5xx' => env('AUDIT_LOG_ERRORS_5XX', true),

        // Include stack trace in the log
        'log_stack_trace' => true,

        // Maximum length for stack trace (in characters)
        'max_stack_trace_length' => 5000,

        // Exceptions that should not be logged
        'excluded_exceptions' => [
            \Illuminate\Auth\AuthenticationException::class,
            \Illuminate\Validation\ValidationException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        ],

        // Retention days for error logs (null = use global retention_days)
        'retention_days' => env('AUDIT_LOG_ERRORS_RETENTION_DAYS', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Send notifications when errors occur.
    | Requires Laravel's mail configuration for email notifications.
    | Requires laravel/slack-notification-channel for Slack notifications.
    |
    */
    'notifications' => [
        // Enable or disable notifications
        'enabled' => env('AUDIT_LOG_NOTIFICATIONS_ENABLED', false),

        // Channels to use: 'mail', 'slack', or both ['mail', 'slack']
        'channels' => explode(',', env('AUDIT_LOG_NOTIFY_CHANNELS', 'mail')),

        // Email configuration (uses Laravel's mail config)
        'mail' => [
            'to' => env('AUDIT_LOG_NOTIFY_EMAIL', null),
        ],

        // Slack configuration
        'slack' => [
            'webhook_url' => env('AUDIT_LOG_SLACK_WEBHOOK', null),
        ],

        // Throttling to prevent notification spam
        'throttle' => [
            'enabled' => true,
            'max_notifications' => 5,   // Max notifications per error type
            'decay_minutes' => 60,      // Time window in minutes
        ],

        // Which response codes trigger notifications
        // Set via env as comma-separated: AUDIT_LOG_NOTIFY_ON_CODES=500,502,503
        'notify_on_codes' => env('AUDIT_LOG_NOTIFY_ON_CODES')
            ? array_map('intval', explode(',', env('AUDIT_LOG_NOTIFY_ON_CODES')))
            : [500, 501, 502, 503, 504],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Logging
    |--------------------------------------------------------------------------
    |
    | Track slow database queries and slow HTTP requests for performance monitoring.
    |
    */
    'performance' => [
        // Enable or disable performance logging
        'enabled' => env('AUDIT_LOG_PERFORMANCE_ENABLED', false),

        // Slow query configuration
        'slow_queries' => [
            'enabled' => env('AUDIT_LOG_SLOW_QUERIES_ENABLED', true),
            // Threshold in milliseconds
            'threshold' => env('AUDIT_LOG_SLOW_QUERY_THRESHOLD', 1000),
            // Whether to log query bindings
            'log_bindings' => env('AUDIT_LOG_SLOW_QUERY_BINDINGS', true),
            // Excluded query patterns (regex)
            'excluded_patterns' => [],
        ],

        // Slow request configuration
        'slow_requests' => [
            'enabled' => env('AUDIT_LOG_SLOW_REQUESTS_ENABLED', true),
            // Threshold in milliseconds
            'threshold' => env('AUDIT_LOG_SLOW_REQUEST_THRESHOLD', 2000),
            // Log memory usage
            'log_memory' => true,
        ],

        // Retention days for performance logs (null = use global retention_days)
        'retention_days' => env('AUDIT_LOG_PERFORMANCE_RETENTION_DAYS', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rollback
    |--------------------------------------------------------------------------
    |
    | Configuration for the audit log rollback feature.
    | Allows reverting model changes based on audit log history.
    |
    */
    'rollback' => [
        // Enable or disable rollback feature
        'enabled' => env('AUDIT_LOG_ROLLBACK_ENABLED', true),

        // User IDs authorized to perform rollback (empty = nobody)
        // Set via env as comma-separated: AUDIT_LOG_ROLLBACK_ALLOWED_USERS=1,5,10
        'allowed_users' => env('AUDIT_LOG_ROLLBACK_ALLOWED_USERS')
            ? array_map('intval', explode(',', env('AUDIT_LOG_ROLLBACK_ALLOWED_USERS')))
            : [],

        // Events that can be rolled back
        'rollbackable_events' => ['created', 'updated', 'deleted'],

        // Maximum chain length for rollback (0 = unlimited)
        'max_chain_length' => env('AUDIT_LOG_ROLLBACK_MAX_CHAIN', 10),

        // Whether to log the rollback as a new audit event
        'log_rollback' => true,
    ],
];
