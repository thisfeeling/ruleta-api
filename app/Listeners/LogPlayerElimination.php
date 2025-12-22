<?php

namespace App\Listeners;

use App\Events\Games\PlayerEliminated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogPlayerElimination implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(PlayerEliminated $event): void
    {
        Log::info('Player eliminated', [
            'player_id' => $event->player->id,
            'player_number' => $event->player->player_number ?? null,
            'game_id' => $event->game->id,
            'reason' => $event->reason,
        ]);
    }
}
