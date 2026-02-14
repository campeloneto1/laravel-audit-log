<?php

namespace Campelo\AuditLog\Listeners;

use Campelo\AuditLog\AuditLogService;
use Illuminate\Database\Events\QueryExecuted;

class SlowQueryListener
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Handle the QueryExecuted event.
     */
    public function handle(QueryExecuted $event): void
    {
        $this->auditLogService->logSlowQuery(
            $event->sql,
            $event->time,
            $event->bindings,
            $event->connectionName
        );
    }
}
