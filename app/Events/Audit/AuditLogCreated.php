<?php

namespace App\Events\Audit;

use App\Models\AuditLog;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuditLogCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AuditLog $log
    ) {}

    public function broadcastOn(): array
    {
        // Only broadcast to supervisor channel
        if ($this->log->show_id) {
            return [
                new PrivateChannel("supervisor.{$this->log->show_id}"),
            ];
        }

        return [];
    }

    public function broadcastAs(): string
    {
        return 'audit.log_created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->log->id,
            'show_id' => $this->log->show_id,
            'event_type' => $this->log->event_type,
            'created_at' => optional($this->log->created_at)?->toIso8601String(),
            'payload_summary' => $this->getSummary(),
        ];
    }

    protected function getSummary(): string
    {
        return match($this->log->event_type) {
            'player_eliminated' => isset($this->log->payload['player_number']) ? "Player #{$this->log->payload['player_number']} eliminated" : $this->log->event_type,
            default => $this->log->event_type,
        };
    }
}
