<?php

namespace App\Events\Show;

use App\Models\Show;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShowStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Show $show
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("show.{$this->show->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'show.started';
    }

    public function broadcastWith(): array
    {
        return [
            'show_id' => $this->show->id,
            'started_at' => optional($this->show->started_at)?->toIso8601String(),
            'current_phase' => $this->show->current_phase ?? null,
            'player_count' => $this->show->current_player_count ?? null,
        ];
    }
}
