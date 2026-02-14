<?php

namespace Campelo\AuditLog\Exceptions;

use Exception;

class RollbackException extends Exception
{
    /**
     * User is not authorized to perform rollback.
     */
    public static function notAuthorized(): self
    {
        return new self('User is not authorized to perform rollback.');
    }

    /**
     * Rollback feature is disabled.
     */
    public static function disabled(): self
    {
        return new self('Rollback feature is disabled.');
    }

    /**
     * Audit log not found.
     */
    public static function auditLogNotFound(int $id): self
    {
        return new self("Audit log with ID {$id} not found.");
    }

    /**
     * Model not found.
     */
    public static function modelNotFound(string $type, mixed $id): self
    {
        return new self("Model {$type} with ID {$id} not found.");
    }

    /**
     * No old values to restore.
     */
    public static function noOldValues(int $auditLogId): self
    {
        return new self("Audit log #{$auditLogId} has no old_values to restore.");
    }

    /**
     * Event cannot be rolled back.
     */
    public static function eventNotRollbackable(string $event): self
    {
        return new self("Event '{$event}' cannot be rolled back.");
    }

    /**
     * Model class does not exist.
     */
    public static function modelClassNotFound(string $type): self
    {
        return new self("Model class {$type} does not exist.");
    }

    /**
     * Rollback chain limit exceeded.
     */
    public static function chainLimitExceeded(int $limit): self
    {
        return new self("Rollback chain limit of {$limit} exceeded.");
    }

    /**
     * Audit log has already been rolled back.
     */
    public static function alreadyRolledBack(int $auditLogId): self
    {
        return new self("Audit log #{$auditLogId} has already been rolled back.");
    }

    /**
     * Audit log does not reference a model.
     */
    public static function noModelReference(): self
    {
        return new self('Audit log does not reference a model.');
    }
}
