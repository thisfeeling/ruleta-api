<?php

namespace App\Events\Games;

use App\Models\{Player, Game};
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerEliminated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Player $player,
        public Game $game,
        public string $reason
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("show.{$this->player->show_id}"),
            new Channel("player.{$this->player->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'player.eliminated';
    }

    public function broadcastWith(): array
    {
        return [
            'player_id' => $this->player->id,
            'player_number' => $this->player->player_number ?? null,
            'game_id' => $this->game->id,
            'game_type' => $this->game->type ?? null,
            'reason' => $this->reason,
            'elimination_order' => $this->player->elimination_order ?? null,
            'eliminated_at' => now()->toIso8601String(),
        ];
    }
}
