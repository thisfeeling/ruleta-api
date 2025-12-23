<?php

namespace App\Events\Games;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use App\Models\{Game, WordSearchGrid};

class WordSearchGridGenerated implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public Game $game;
    public WordSearchGrid $grid;

    public function __construct(Game $game, WordSearchGrid $grid)
    {
        $this->game = $game;
        $this->grid = $grid;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('show.' . $this->game->show_id);
    }

    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->game->id,
            'grid_id' => $this->grid->id,
            'time_limit_seconds' => $this->grid->time_limit_seconds,
            'words' => $this->grid->words,
        ];
    }
}
