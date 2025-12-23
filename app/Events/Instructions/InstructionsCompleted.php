<?php

namespace App\Events\Instructions;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InstructionsCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Game $game) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("show.{$this->game->show_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'instructions.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->game->id,
        ];
    }
}
