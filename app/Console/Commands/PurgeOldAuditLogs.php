<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Audit\AuditService;

class PurgeOldAuditLogs extends Command
{
    protected $signature = 'audit:purge {--days=30}';
    protected $description = 'Purge audit logs older than specified days';

    public function handle(AuditService $audit): int
    {
        $days = (int) $this->option('days');

        $this->info("Purging audit logs older than {$days} days...");

        $deleted = $audit->purgeOldLogs($days);

        $this->info("✅ Deleted {$deleted} audit log entries");

        return Command::SUCCESS;
    }
}
