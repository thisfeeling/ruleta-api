<?php

namespace App\Events\Show;

use App\Models\Show;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PhaseChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Show $show,
        public string $fromPhase,
        public string $toPhase
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("show.{$this->show->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'show.phase_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'show_id' => $this->show->id,
            'from_phase' => $this->fromPhase,
            'to_phase' => $this->toPhase,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
