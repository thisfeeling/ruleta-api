<?php

namespace App\Services\Game;

use App\Models\{Player, Game, Show};
use App\Events\Games\PlayerEliminated;
use Illuminate\Support\Collection;

class EliminationService
{
    /**
     * Eliminate players based on performance
     */
    public function eliminatePlayers(Game $game, Collection $players, string $reason): array
    {
        $eliminated = [];
        $show = $game->show;
        $nextOrder = $this->getNextEliminationOrder($show);

        foreach ($players as $player) {
            if ($player->status === 'active') {
                $player->eliminate($game->type, $nextOrder++);

                event(new PlayerEliminated($player, $game, $reason));

                $eliminated[] = $player->id;
            }
        }

        $game->increment('players_eliminated', count($eliminated));
        $show->decrement('current_player_count', count($eliminated));

        return $eliminated;
    }

    /**
     * Calculate dynamic elimination count
     */
    public function calculateEliminationCount(int $activeCount, string $gameType): int
    {
        return match($gameType) {
            'millionaire' => (int) ceil($activeCount * 0.4), // 40%
            'rope' => (int) ceil($activeCount * 0.25),       // 25%
            'spell' => (int) ceil($activeCount * 0.3),       // 30%
            'roulette' => $activeCount - 1,                  // All but 1
            default => 0,
        };
    }

    /**
     * Get worst performing players
     */
    public function getWorstPerformers(Collection $players, int $count): Collection
    {
        return $players->sortBy('total_score')->take($count);
    }

    protected function getNextEliminationOrder(Show $show): int
    {
        $max = Player::where('show_id', $show->id)
            ->whereNotNull('elimination_order')
            ->max('elimination_order');

        return ($max ?? 0) + 1;
    }
}
