<?php

namespace App\Events\Scoreboard;

use App\Models\Show;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScoreboardUpdated implements ShouldBroadcast
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
        return 'scoreboard.updated';
    }

    public function broadcastWith(): array
    {
        $scoreboard = app(\App\Services\Scoreboard\ScoreboardService::class)
            ->getScoreboard($this->show);

        return [
            'show_id' => $this->show->id,
            'scoreboard' => $scoreboard,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
