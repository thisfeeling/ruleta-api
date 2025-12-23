<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Show;
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
