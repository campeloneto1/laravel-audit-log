<?php

namespace Campelo\AuditLog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Who
            'user' => [
                'id' => $this->user_id,
                'type' => $this->user_type,
                'name' => $this->user_name,
                'email' => $this->user_email,
            ],

            // When
            'performed_at' => $this->performed_at?->toIso8601String(),
            'performed_at_human' => $this->performed_at?->diffForHumans(),

            // Where
            'request' => [
                'ip' => $this->ip_address,
                'user_agent' => $this->user_agent,
                'url' => $this->url,
                'method' => $this->method,
                'route' => $this->route_name,
            ],

            // What
            'event' => $this->event,
            'model' => [
                'type' => $this->auditable_type,
                'id' => $this->auditable_id,
                'table' => $this->table_name,
            ],

            // Changes
            'changes' => [
                'old' => $this->old_values,
                'new' => $this->new_values,
                'fields' => $this->changed_fields,
                'diff' => $this->changes, // Computed attribute
            ],

            // Extra
            'request_data' => $this->request_data,
            'metadata' => $this->metadata,
            'description' => $this->description,
            'summary' => $this->summary, // Computed attribute
            'response_code' => $this->response_code,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
