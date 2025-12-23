<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Artisan;
use App\Services\Audit\AuditService;
use App\Models\AuditLog;
use App\Jobs\BackupAuditToS3;
use App\Events\Audit\AuditLogCreated;

class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_dispatches_backup_job_and_broadcasts_event()
    {
        Bus::fake();
        Event::fake();

        $service = $this->app->make(AuditService::class);

        $log = $service->log('system_event', ['foo' => 'bar'], null, null, 'TestEntity', 123);

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertEquals('system_event', $log->event_type);
        $this->assertEquals('TestEntity', $log->entity_type);

        Bus::assertDispatched(BackupAuditToS3::class);
        Event::assertDispatched(AuditLogCreated::class, function ($e) use ($log) {
            return $e->log->id === $log->id;
        });
    }
}
