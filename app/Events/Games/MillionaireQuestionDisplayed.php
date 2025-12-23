<?php

namespace App\Events\Games;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
use App\Models\{Game, MillionaireQuestion};

class MillionaireQuestionDisplayed implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public Game $game;
    public MillionaireQuestion $question;

    public function __construct(Game $game, MillionaireQuestion $question)
    {
        $this->game = $game;
        $this->question = $question;
    }

    public function broadcastOn()
    {
        // broadcast on show scoped channel
        return new PrivateChannel('show.' . $this->game->show_id);
    }

    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->game->id,
            'question' => [
                'id' => $this->question->id,
                'question_number' => $this->question->question_number,
                'time_limit_seconds' => $this->question->time_limit_seconds,
            ],
        ];
    }
}
