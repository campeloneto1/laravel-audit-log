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
];
