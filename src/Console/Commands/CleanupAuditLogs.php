<?php

namespace Campelo\AuditLog\Console\Commands;

use Campelo\AuditLog\Models\AuditLog;
use Illuminate\Console\Command;

class CleanupAuditLogs extends Command
{
    protected $signature = 'audit-log:cleanup
                            {--days= : Days to keep (overrides config)}
                            {--error-days= : Days to keep error logs (overrides config)}
                            {--type= : Type to clean: all, errors, operations}
                            {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Clean up old audit log entries based on retention policy';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $type = $this->option('type') ?? 'all';

        if ($dryRun) {
            $this->info('DRY RUN - No records will be deleted');
            $this->newLine();
        }

        $totalDeleted = 0;

        if ($type === 'all' || $type === 'operations') {
            $totalDeleted += $this->cleanupOperations($dryRun);
        }

        if ($type === 'all' || $type === 'errors') {
            $totalDeleted += $this->cleanupErrors($dryRun);
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("Would delete {$totalDeleted} records in total.");
        } else {
            $this->info("Cleanup complete. Deleted {$totalDeleted} records.");
        }

        return self::SUCCESS;
    }

    protected function cleanupOperations(bool $dryRun): int
    {
        $days = $this->option('days') ?? config('audit-log.retention_days');

        if ($days === null) {
            $this->line('Operations: No retention period set, skipping...');
            return 0;
        }

        $cutoffDate = now()->subDays((int) $days);

        $query = AuditLog::where('event', '!=', 'error')
            ->where('performed_at', '<', $cutoffDate);

        $count = $query->count();

        if ($count === 0) {
            $this->line("Operations: No records older than {$days} days.");
            return 0;
        }

        if ($dryRun) {
            $this->line("Operations: Would delete {$count} records older than {$days} days.");
        } else {
            $query->delete();
            $this->line("Operations: Deleted {$count} records older than {$days} days.");
        }

        return $count;
    }

    protected function cleanupErrors(bool $dryRun): int
    {
        $days = $this->option('error-days')
            ?? config('audit-log.errors.retention_days')
            ?? config('audit-log.retention_days');

        if ($days === null) {
            $this->line('Errors: No retention period set, skipping...');
            return 0;
        }

        $cutoffDate = now()->subDays((int) $days);

        $query = AuditLog::where('event', 'error')
            ->where('performed_at', '<', $cutoffDate);

        $count = $query->count();

        if ($count === 0) {
            $this->line("Errors: No records older than {$days} days.");
            return 0;
        }

        if ($dryRun) {
            $this->line("Errors: Would delete {$count} records older than {$days} days.");
        } else {
            $query->delete();
            $this->line("Errors: Deleted {$count} records older than {$days} days.");
        }

        return $count;
    }
}
