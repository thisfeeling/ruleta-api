# 15 - Audit System

**Status**: [x] Completed

## Objetivo

Implementar sistema de auditoría dual: DB (30 días) + S3 (permanente).

## Dependencias

- **Anterior**: 14 - Achievement System

## Implementación

### 15.1 Audit Service

```php
<?php

namespace App\Services\Audit;

use App\Models\{AuditLog, Show};
use App\Services\Storage\StorageService;
use App\Events\Audit\AuditLogCreated;

class AuditService
{
    protected StorageService $storage;

    public function __construct(StorageService $storage)
    {
        $this->storage = $storage;
    }

    public function log(
        string $eventType,
        array $payload,
        ?int $showId = null,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): AuditLog {
        $log = AuditLog::log($eventType, $payload, $showId, $userId, $entityType, $entityId);
        
        // Async backup to S3
        \App\Jobs\BackupAuditToS3::dispatch($log)->onQueue('audit');
        
        // Broadcast to supervisor
        event(new AuditLogCreated($log));
        
        return $log;
    }

    public function exportShow(Show $show): string
    {
        $logs = AuditLog::where('show_id', $show->id)
            ->orderBy('created_at')
            ->get();
        
        $date = now()->format('Y-m-d');
        $path = $this->storage->uploadAuditBackup($show->id, $date, $logs->toArray());
        
        return $this->storage->getSignedUrl($path, 1440); // 24h
    }

    public function purgeOldLogs(int $daysToKeep = 30): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        return AuditLog::where('created_at', '<', $cutoffDate)->delete();
    }
}
```

### 15.2 S3 Backup Job

```bash
php artisan make:job BackupAuditToS3
```

```php
<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Services\Storage\StorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BackupAuditToS3 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected AuditLog $log
    ) {}

    public function handle(StorageService $storage): void
    {
        $date = $this->log->created_at->format('Y-m-d');
        $filename = "audit/show-{$this->log->show_id}/{$date}/{$this->log->id}.json";
        
        $storage->upload($filename, json_encode($this->log->toArray()));
        
        $this->log->update(['s3_backup_key' => $filename]);
    }
}
```

### 15.3 Audit Console Commands

```bash
php artisan make:command PurgeOldAuditLogs
```

```php
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
```

Schedule in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('audit:purge')->daily();
}
```

## Próximos Pasos

→ **16 - Instructions System**: Pre-game instructions
→ **17 - Scoreboard System**: Unified scoring
→ **18 - Testing**: Unit & feature tests
