<?php

namespace App\Events\Games;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use App\Models\{Game, Player, MillionaireQuestion};

class MillionaireAnswerSubmitted implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public Game $game;
    public Player $player;
    public MillionaireQuestion $question;
    public bool $isCorrect;

    public function __construct(Game $game, Player $player, MillionaireQuestion $question, bool $isCorrect)
    {
        $this->game = $game;
        $this->player = $player;
        $this->question = $question;
        $this->isCorrect = $isCorrect;
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
            'question_id' => $this->question->id,
            'is_correct' => $this->isCorrect,
        ];
    }
}
