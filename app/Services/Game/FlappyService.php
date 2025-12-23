<?php

namespace App\Services\Game;

use App\Models\{Game, Player, FlappyAttempt};
use App\Services\Game\Contracts\GameServiceInterface;

class FlappyService implements GameServiceInterface
{
    public function start(Game $game): void
    {
        $game->update(['state' => ['active' => true]]);
    }

    public function handlePlayerAction(Game $game, Player $player, array $action): void
    {
        if (($action['type'] ?? '') === 'crashed') {
            $this->recordAttempt($game, $player, $action);
        }
    }

    public function complete(Game $game): array
    {
        return ['eliminated_count' => 0, 'eliminated_players' => []];
    }

    public function getState(Game $game): array
    {
        return $game->state ?? [];
    }

    protected function recordAttempt(Game $game, Player $player, array $data): void
    {
        FlappyAttempt::create([
            'game_id' => $game->id,
            'player_id' => $player->id,
            'survival_time_ms' => $data['survival_time_ms'] ?? 0,
            'pipes_passed' => $data['pipes_passed'] ?? 0,
            'score' => $data['score'] ?? 0,
        ]);
    }
}
