<?php

namespace App\Events\Scoreboard;

use App\Models\PlayerScore;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScoreAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PlayerScore $score
    ) {}

    public function broadcastOn(): array
    {
        $player = $this->score->player;

        return [
            new Channel("show.{$player->show_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'scoreboard.score_added';
    }

    public function broadcastWith(): array
    {
        $player = $this->score->player;

        return [
            'player_id' => $player->id,
            'player_number' => $player->player_number ?? null,
            'score' => $this->score->score,
            'metadata' => $this->score->metadata ?? null,
        ];
    }
}
