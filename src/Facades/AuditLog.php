<?php

namespace Campelo\AuditLog\Facades;

use Campelo\AuditLog\AuditLogService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Campelo\AuditLog\Models\AuditLog|null log(string $event, ?\Illuminate\Database\Eloquent\Model $model = null, ?array $oldValues = null, ?array $newValues = null, ?string $description = null, ?array $metadata = null)
 * @method static \Campelo\AuditLog\Models\AuditLog|null logModelEvent(\Illuminate\Database\Eloquent\Model $model, string $event)
 * @method static \Campelo\AuditLog\Models\AuditLog|null logRequest(\Illuminate\Http\Request $request, \Symfony\Component\HttpFoundation\Response $response)
 * @method static \Campelo\AuditLog\Models\AuditLog|null logError(\Throwable $exception, ?\Illuminate\Http\Request $request = null, ?array $context = null)
 *
 * @see \Campelo\AuditLog\AuditLogService
 */
class AuditLog extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AuditLogService::class;
    }
}
