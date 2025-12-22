<?php

namespace App\Services\Broadcasting;

use Illuminate\Support\Facades\Log;

class BroadcastService
{
    /**
     * Broadcast show event
     */
    public function broadcastToShow(int $showId, string $event, array $data): void
    {
        broadcast(new \Illuminate\Broadcasting\BroadcastEvent($event, $data))
            ->toOthers();

        Log::debug("Broadcast to show", ['show_id' => $showId, 'event' => $event]);
    }

    /**
     * Broadcast game event
     */
    public function broadcastToGame(int $gameId, string $event, array $data): void
    {
        broadcast(new \Illuminate\Broadcasting\BroadcastEvent($event, $data))
            ->toOthers();

        Log::debug("Broadcast to game", ['game_id' => $gameId, 'event' => $event]);
    }

    /**
     * Broadcast to specific player
     */
    public function broadcastToPlayer(int $playerId, string $event, array $data): void
    {
        broadcast(new \Illuminate\Broadcasting\BroadcastEvent($event, $data))
            ->toOthers();

        Log::debug("Broadcast to player", ['player_id' => $playerId, 'event' => $event]);
    }
}
