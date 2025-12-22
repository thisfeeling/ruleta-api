<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\{Show, Player, User};

// Public channel - show lobby
Broadcast::channel('show.{showId}', function (User $user, int $showId) {
    return true; // Public access
});

// Player channel - private player data
Broadcast::channel('player.{playerId}', function (User $user, int $playerId) {
    return $user->players()->where('id', $playerId)->exists();
});

// Supervisor channel - admin controls
Broadcast::channel('supervisor.{showId}', function (User $user, int $showId) {
    return $user->isSupervisor();
});

// Game channel - specific game events
Broadcast::channel('game.{gameId}', function (User $user, int $gameId) {
    $game = \App\Models\Game::find($gameId);
    if (!$game) return false;

    return $user->players()->where('show_id', $game->show_id)->exists()
        || $user->isSupervisor();
});

// Presence channel - who's online
Broadcast::channel('presence.show.{showId}', function (User $user, int $showId) {
    $player = $user->players()->where('show_id', $showId)->first();

    if ($player) {
        return [
            'id' => $user->id,
            'player_id' => $player->id,
            'player_number' => $player->player_number,
            'name' => $user->name,
            'role' => $user->role,
        ];
    }

    return false;
});
