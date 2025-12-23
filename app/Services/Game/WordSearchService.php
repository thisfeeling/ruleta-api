<?php

namespace App\Services\Game;

use App\Models\{Game, Player, WordSearchGrid, WordSearchFind};
use App\Services\Game\Contracts\GameServiceInterface;

class WordSearchService implements GameServiceInterface
{
    public function start(Game $game): void
    {
        $grid = $this->generateGrid($game);

        $game->update(['state' => ['grid_id' => $grid->id]]);

        event(new \App\Events\Games\WordSearchGridGenerated($game, $grid));
    }

    public function handlePlayerAction(Game $game, Player $player, array $action): void
    {
        if (($action['type'] ?? '') === 'found_word') {
            $this->recordFind($game, $player, $action['word'] ?? '', $action['time_elapsed_ms'] ?? 0);
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

    protected function generateGrid(Game $game): WordSearchGrid
    {
        $grid = array_fill(0, 15, array_fill(0, 15, ''));
        $words = ['FAMILIA', 'JUEGO', 'RULETA', 'PREMIO'];

        return WordSearchGrid::create([
            'game_id' => $game->id,
            'grid' => $grid,
            'words' => $words,
            'time_limit_seconds' => 180,
        ]);
    }

    protected function recordFind(Game $game, Player $player, string $word, int $timeMs): void
    {
        $grid = WordSearchGrid::where('game_id', $game->id)->first();

        if ($grid) {
            $findOrder = WordSearchFind::where('grid_id', $grid->id)->count() + 1;

            WordSearchFind::create([
                'grid_id' => $grid->id,
                'player_id' => $player->id,
                'word' => $word,
                'find_order' => $findOrder,
                'time_elapsed_ms' => $timeMs,
            ]);

            event(new \App\Events\Games\WordFound($game, $player, $grid, $word, $findOrder));
        }
    }
}
