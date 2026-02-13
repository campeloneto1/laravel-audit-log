<?php

namespace Campelo\AuditLog\Jobs;

use Campelo\AuditLog\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WriteAuditLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected array $data
    ) {}

    public function handle(): void
    {
        AuditLog::create($this->data);
    }
}
