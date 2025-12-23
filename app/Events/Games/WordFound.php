<?php

namespace App\Events\Games;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use App\Models\{Game, Player, WordSearchGrid};

class WordFound implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public Game $game;
    public Player $player;
    public WordSearchGrid $grid;
    public string $word;
    public int $findOrder;

    public function __construct(Game $game, Player $player, WordSearchGrid $grid, string $word, int $findOrder)
    {
        $this->game = $game;
        $this->player = $player;
        $this->grid = $grid;
        $this->word = $word;
        $this->findOrder = $findOrder;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('show.' . $this->game->show_id);
    }

    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->game->id,
            'player_id' => $this->player->id,
            'grid_id' => $this->grid->id,
            'word' => $this->word,
            'find_order' => $this->findOrder,
        ];
    }
}
