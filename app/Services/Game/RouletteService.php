<?php

namespace App\Services\Game;

use App\Models\{Game, Player, RouletteSpin};
use App\Services\Game\Contracts\GameServiceInterface;

class RouletteService implements GameServiceInterface
{
    public function start(Game $game): void
    {
        $game->update(['state' => ['round' => 1, 'active_players' => []]]);
    }

    public function handlePlayerAction(Game $game, Player $player, array $action): void
    {
        if (($action['type'] ?? '') === 'spin') {
            $this->recordSpin($game, $player);
        }
    }

    public function complete(Game $game): array
    {
        $lowestScore = RouletteSpin::where('game_id', $game->id)
            ->selectRaw('player_id, SUM(points_won) as total')
            ->groupBy('player_id')
            ->orderBy('total')
            ->first();

        if ($lowestScore) {
            $player = Player::find($lowestScore->player_id);
            $eliminated = app(EliminationService::class)->eliminatePlayers(
                $game,
                collect([$player]),
                'lowest_roulette_score'
            );

            return ['eliminated_count' => 1, 'eliminated_players' => $eliminated];
        }

        return ['eliminated_count' => 0, 'eliminated_players' => []];
    }

    public function getState(Game $game): array
    {
        return $game->state ?? [];
    }

    protected function recordSpin(Game $game, Player $player): void
    {
        $points = rand(0, 100) * 10; // 0-1000

        RouletteSpin::create([
            'game_id' => $game->id,
            'player_id' => $player->id,
            'round_number' => $game->state['round'] ?? 1,
            'points_won' => $points,
            'cumulative_score' => $this->getCumulativeScore($game, $player) + $points,
            'spin_duration_seconds' => 3.5,
        ]);
    }

    protected function getCumulativeScore(Game $game, Player $player): int
    {
        return RouletteSpin::where('game_id', $game->id)
            ->where('player_id', $player->id)
            ->sum('points_won');
    }
}
