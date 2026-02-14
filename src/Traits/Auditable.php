<?php

namespace Campelo\AuditLog\Traits;

use Campelo\AuditLog\Models\AuditLog;
use Campelo\AuditLog\AuditLogService;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Auditable
{
    /**
     * Boot the auditable trait.
     */
    public static function bootAuditable(): void
    {
        if (!config('audit-log.enabled', true)) {
            return;
        }

        $events = config('audit-log.log_events', ['created', 'updated', 'deleted']);

        if (in_array('created', $events)) {
            static::created(function ($model) {
                app(AuditLogService::class)->logModelEvent($model, 'created');
            });
        }

        if (in_array('updated', $events)) {
            static::updated(function ($model) {
                app(AuditLogService::class)->logModelEvent($model, 'updated');
            });
        }

        if (in_array('deleted', $events)) {
            static::deleted(function ($model) {
                app(AuditLogService::class)->logModelEvent($model, 'deleted');
            });
        }

        if (in_array('restored', $events)) {
            if (method_exists(static::class, 'restored')) {
                static::restored(function ($model) {
                    app(AuditLogService::class)->logModelEvent($model, 'restored');
                });
            }
        }

        if (in_array('retrieved', $events)) {
            static::retrieved(function ($model) {
                app(AuditLogService::class)->logModelEvent($model, 'retrieved');
            });
        }
    }

    /**
     * Get all audit logs for this model.
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')
            ->orderByDesc('performed_at');
    }

    /**
     * Get the last audit log for this model.
     */
    public function lastAuditLog(): ?AuditLog
    {
        return $this->auditLogs()->first();
    }

    /**
     * Get audit logs for a specific event.
     */
    public function getAuditLogsForEvent(string $event): \Illuminate\Database\Eloquent\Collection
    {
        return $this->auditLogs()->where('event', $event)->get();
    }

    /**
     * Get the fields that should be excluded from audit logging.
     */
    public function getAuditExclude(): array
    {
        return $this->auditExclude ?? [];
    }

    /**
     * Get the fields that should be included in audit logging.
     * If empty, all fields are included (except excluded ones).
     */
    public function getAuditInclude(): array
    {
        return $this->auditInclude ?? [];
    }

    /**
     * Get custom audit data to be stored with the log.
     */
    public function getAuditCustomData(): array
    {
        return [];
    }

    /**
     * Determine if the model should be audited.
     */
    public function shouldBeAudited(): bool
    {
        return true;
    }

    /**
     * Get a description for the audit log.
     */
    public function getAuditDescription(string $event): ?string
    {
        return null;
    }

    // =========================================================================
    // ROLLBACK METHODS
    // =========================================================================

    /**
     * Rollback this model to a specific audit log state.
     *
     * @param int $auditLogId The audit log ID to rollback to
     * @return AuditLog|null The rollback audit log entry
     * @throws \Campelo\AuditLog\Exceptions\RollbackException
     */
    public function rollbackTo(int $auditLogId): ?AuditLog
    {
        $auditLog = AuditLog::where('id', $auditLogId)
            ->where('auditable_type', get_class($this))
            ->where('auditable_id', $this->getKey())
            ->firstOrFail();

        return app(AuditLogService::class)->rollback($auditLog->id);
    }

    /**
     * Rollback this model to its previous state.
     *
     * @return AuditLog|null The rollback audit log entry
     * @throws \Campelo\AuditLog\Exceptions\RollbackException
     */
    public function rollbackToPrevious(): ?AuditLog
    {
        $rollbackableEvents = config('audit-log.rollback.rollbackable_events', ['created', 'updated', 'deleted']);

        $lastChange = $this->auditLogs()
            ->whereIn('event', $rollbackableEvents)
            ->first();

        if (!$lastChange) {
            return null;
        }

        return app(AuditLogService::class)->rollback($lastChange->id);
    }
}
