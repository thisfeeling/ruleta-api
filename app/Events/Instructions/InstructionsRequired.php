<?php

namespace App\Events\Instructions;

use App\Models\{Game, GameInstruction};
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InstructionsRequired implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public GameInstruction $instruction
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("show.{$this->game->show_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'instructions.required';
    }

    public function broadcastWith(): array
    {
        // Support both legacy model (title/body/locale) and migration schema (content_es/content_en)
        $locale = $this->instruction->locale ?? 'es';

        $content = null;
        if (property_exists($this->instruction, 'content_' . $locale)) {
            $content = $this->instruction->{'content_' . $locale};
        } elseif (isset($this->instruction->content)) {
            $content = $this->instruction->content;
        }

        return [
            'game_id' => $this->game->id,
            'instruction' => [
                'id' => $this->instruction->id ?? null,
                'title' => $this->instruction->title ?? null,
                'content' => $content,
                'locale' => $locale,
            ],
        ];
    }
}
