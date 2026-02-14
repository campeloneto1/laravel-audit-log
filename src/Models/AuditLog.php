<?php

namespace Campelo\AuditLog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'request_data' => 'array',
        'metadata' => 'array',
        'performed_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('audit-log.table', 'audit_logs');
        $this->connection = config('audit-log.connection');
    }

    /**
     * Get the auditable model.
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user that performed the action.
     */
    public function user(): BelongsTo
    {
        $userModel = config('audit-log.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * Scope to filter by event type.
     */
    public function scopeEvent(Builder $query, string|array $event): Builder
    {
        if (is_array($event)) {
            return $query->whereIn('event', $event);
        }

        return $query->where('event', $event);
    }

    /**
     * Scope to filter by auditable model.
     */
    public function scopeForModel(Builder $query, Model|string $model, int|string|null $id = null): Builder
    {
        if ($model instanceof Model) {
            return $query
                ->where('auditable_type', get_class($model))
                ->where('auditable_id', $model->getKey());
        }

        $query->where('auditable_type', $model);

        if ($id !== null) {
            $query->where('auditable_id', $id);
        }

        return $query;
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser(Builder $query, int|Model $user): Builder
    {
        $userId = $user instanceof Model ? $user->getKey() : $user;

        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by table name.
     */
    public function scopeForTable(Builder $query, string $table): Builder
    {
        return $query->where('table_name', $table);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeBetween(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('performed_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by IP address.
     */
    public function scopeFromIp(Builder $query, string $ip): Builder
    {
        return $query->where('ip_address', $ip);
    }

    /**
     * Scope to filter by HTTP method.
     */
    public function scopeMethod(Builder $query, string|array $method): Builder
    {
        if (is_array($method)) {
            return $query->whereIn('method', array_map('strtoupper', $method));
        }

        return $query->where('method', strtoupper($method));
    }

    /**
     * Scope to get only write operations (POST, PUT, PATCH, DELETE).
     */
    public function scopeWriteOperations(Builder $query): Builder
    {
        return $query->whereIn('method', ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    /**
     * Scope to get only read operations (GET).
     */
    public function scopeReadOperations(Builder $query): Builder
    {
        return $query->where('method', 'GET');
    }

    /**
     * Scope to get only error logs.
     */
    public function scopeErrors(Builder $query): Builder
    {
        return $query->where('event', 'error');
    }

    /**
     * Scope to get only server error logs (5xx).
     */
    public function scopeServerErrors(Builder $query): Builder
    {
        return $query->where('event', 'error')
            ->where('response_code', '>=', 500)
            ->where('response_code', '<', 600);
    }

    /**
     * Scope to get only client error logs (4xx).
     */
    public function scopeClientErrors(Builder $query): Builder
    {
        return $query->where('event', 'error')
            ->where('response_code', '>=', 400)
            ->where('response_code', '<', 500);
    }

    /**
     * Scope to filter by response code.
     */
    public function scopeResponseCode(Builder $query, int|array $code): Builder
    {
        if (is_array($code)) {
            return $query->whereIn('response_code', $code);
        }

        return $query->where('response_code', $code);
    }

    /**
     * Get the changes as a human-readable diff.
     */
    public function getChangesAttribute(): array
    {
        $changes = [];
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($allKeys as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Get a summary of what changed.
     */
    public function getSummaryAttribute(): string
    {
        $model = class_basename($this->auditable_type ?? 'Record');
        $action = ucfirst($this->event);

        $summary = "{$action} {$model}";

        if ($this->auditable_id) {
            $summary .= " #{$this->auditable_id}";
        }

        if ($this->user_name) {
            $summary .= " by {$this->user_name}";
        }

        return $summary;
    }
}
