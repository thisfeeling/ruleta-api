<?php

namespace App\Events\Games;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public array $results = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("show.{$this->game->show_id}"),
            new Channel("game.{$this->game->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'game.ended';
    }

    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->game->id,
            'type' => $this->game->type ?? null,
            'round_number' => $this->game->round_number ?? null,
            'is_bonus' => (bool) ($this->game->is_bonus ?? false),
            'results' => $this->results,
            'completed_at' => optional($this->game->completed_at)?->toIso8601String(),
        ];
    }
}
