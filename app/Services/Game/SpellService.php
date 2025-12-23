<?php

namespace App\Services\Game;

use App\Models\{Game, Player, SpellWord, SpellAttempt};
use App\Services\Game\Contracts\GameServiceInterface;

class SpellService implements GameServiceInterface
{
    public function start(Game $game): void
    {
        $this->assignWords($game);
    }

    public function handlePlayerAction(Game $game, Player $player, array $action): void
    {
        if (($action['type'] ?? '') === 'submit_spelling') {
            $this->submitSpelling($game, $player, $action['spelling'] ?? '');
        }
    }

    public function complete(Game $game): array
    {
        $incorrect = SpellWord::where('game_id', $game->id)
            ->whereHas('attempts', fn($q) => $q->where('is_correct', false))
            ->with('player')
            ->get();

        $eliminated = app(EliminationService::class)->eliminatePlayers(
            $game,
            $incorrect->pluck('player'),
            'incorrect_spelling'
        );

        return ['eliminated_count' => count($eliminated), 'eliminated_players' => $eliminated];
    }

    public function getState(Game $game): array
    {
        return $game->state ?? [];
    }

    protected function assignWords(Game $game): void
    {
        $players = Player::where('show_id', $game->show_id)->where('status', 'active')->get();

        foreach ($players as $player) {
            SpellWord::create([
                'game_id' => $game->id,
                'player_id' => $player->id,
                'word' => $this->getRandomWord(),
                'difficulty' => 'medium',
                'time_limit_seconds' => 60,
            ]);
        }
    }

    protected function getRandomWord(): string
    {
        $words = ['mariposa', 'exuberante', 'orquesta', 'paradigma'];
        return $words[array_rand($words)];
    }

    protected function submitSpelling(Game $game, Player $player, string $spelling): void
    {
        $word = SpellWord::where('game_id', $game->id)
            ->where('player_id', $player->id)
            ->first();

        if ($word) {
            SpellAttempt::create([
                'spell_word_id' => $word->id,
                'submitted_spelling' => $spelling,
                'is_correct' => strtolower($spelling) === strtolower($word->word),
                'time_taken_ms' => 0,
            ]);
        }
    }
}
