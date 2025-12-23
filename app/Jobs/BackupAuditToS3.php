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
