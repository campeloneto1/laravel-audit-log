<?php

use Campelo\AuditLog\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('audit-log.route_prefix', 'api/audit-logs'))
    ->middleware(config('audit-log.route_middleware', ['api', 'auth']))
    ->name('audit-log.')
    ->group(function () {
        // List with filters
        Route::get('/', [AuditLogController::class, 'index'])->name('index');

        // Statistics
        Route::get('/stats', [AuditLogController::class, 'stats'])->name('stats');

        // Filter options
        Route::get('/filters', [AuditLogController::class, 'filters'])->name('filters');

        // Cleanup old logs
        Route::delete('/cleanup', [AuditLogController::class, 'cleanup'])->name('cleanup');

        // Get logs for specific model
        Route::get('/model/{model}/{id}', [AuditLogController::class, 'forModel'])
            ->where('model', '.*')
            ->name('for-model');

        // Get logs for specific user
        Route::get('/user/{userId}', [AuditLogController::class, 'forUser'])->name('for-user');

        // Rollback chain
        Route::post('/rollback-chain/{id}', [AuditLogController::class, 'rollbackChain'])->name('rollback-chain');

        // Check if can rollback
        Route::get('/{id}/can-rollback', [AuditLogController::class, 'canRollback'])->name('can-rollback');

        // Perform rollback
        Route::post('/{id}/rollback', [AuditLogController::class, 'rollback'])->name('rollback');

        // Show single entry
        Route::get('/{id}', [AuditLogController::class, 'show'])->name('show');
    });
