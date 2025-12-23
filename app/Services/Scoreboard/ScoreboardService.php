<?php

namespace App\Services\Scoreboard;

use App\Models\{Game, Player, PlayerScore, Show};
use App\Events\Scoreboard\{ScoreAdded, ScoreboardUpdated};
use Illuminate\Support\Collection;

class ScoreboardService
{
    public function addScore(
        Player $player,
        Game $game,
        int $rawScore,
        ?array $metadata = null
    ): PlayerScore {
        $normalizedScore = $this->normalize($game->type, $rawScore, $metadata);

        $score = PlayerScore::create([
            'player_id' => $player->id,
            'game_id' => $game->id,
            'game_type' => $game->type,
            'raw_score' => $rawScore,
            'normalized_score' => $normalizedScore,
            'metadata' => $metadata,
        ]);

        event(new ScoreAdded($score));

        $this->updatePlayerTotal($player);

        return $score;
    }

    public function getScoreboard(Show $show): Collection
    {
        $players = Player::where('show_id', $show->id)
            ->withSum('scores', 'normalized_score')
            ->with(['user', 'scores' => fn($q) => $q->with('game')])
            ->orderByDesc('scores_sum_normalized_score')
            ->get();

        return $players->map(fn($player, $index) => [
            'rank' => $index + 1,
            'player_id' => $player->id,
            'player_number' => $player->player_number,
            'name' => $player->user->name ?? null,
            'status' => $player->status,
            'total_score' => $player->scores_sum_normalized_score ?? 0,
            'game_scores' => $player->scores->map(fn($score) => [
                'game_type' => $score->game_type,
                'normalized_score' => $score->normalized_score,
                'raw_score' => $score->raw_score,
            ]),
        ]);
    }

    public function getTopPlayers(Show $show, int $limit = 10): Collection
    {
        return $this->getScoreboard($show)->take($limit);
    }

    public function getPlayerRank(Player $player): int
    {
        $scoreboard = $this->getScoreboard($player->show);

        $position = $scoreboard->search(fn($entry) => $entry['player_id'] === $player->id);

        return $position !== false ? $position + 1 : 0;
    }

    protected function normalize(string $gameType, int $rawScore, ?array $metadata = null): int
    {
        return match($gameType) {
            'millionaire' => $this->normalizeMillionaire($rawScore, $metadata),
            'rope' => $this->normalizeRope($rawScore),
            'spell' => $this->normalizeSpell($rawScore, $metadata),
            'roulette' => $this->normalizeRoulette($rawScore),
            'word_search' => $this->normalizeWordSearch($rawScore, $metadata),
            'flappy' => $this->normalizeFlappy($rawScore),
            default => 0,
        };
    }

    protected function normalizeMillionaire(int $correctCount, ?array $metadata): int
    {
        $total = $metadata['total_questions'] ?? 10;
        if ($total === 0) return 0;
        return (int) (($correctCount / $total) * 1000);
    }

    protected function normalizeRope(int $clicks): int
    {
        return min(1000, (int) (($clicks / 200) * 1000));
    }

    protected function normalizeSpell(int $correct, ?array $metadata): int
    {
        if ($correct === 0) return 0;

        $timeMs = $metadata['time_taken_ms'] ?? 0;
        $timeLimit = $metadata['time_limit_seconds'] ?? 60;
        $timeRatio = $timeMs / ($timeLimit * 1000);

        return (int) ((1 - $timeRatio) * 1000);
    }

    protected function normalizeRoulette(int $points): int
    {
        return min(1000, $points);
    }

    protected function normalizeWordSearch(int $wordsFound, ?array $metadata): int
    {
        $baseScore = $wordsFound * 100;
        $timeMs = $metadata['time_elapsed_ms'] ?? 180000;
        $timeBonus = max(0, (180000 - $timeMs) / 180);

        return min(1000, (int) ($baseScore + $timeBonus));
    }

    protected function normalizeFlappy(int $survivalTimeMs): int
    {
        return min(1000, (int) ($survivalTimeMs / 100));
    }

    protected function updatePlayerTotal(Player $player): void
    {
        $total = PlayerScore::where('player_id', $player->id)
            ->sum('normalized_score');

        $player->update(['total_score' => $total]);

        event(new ScoreboardUpdated($player->show));
    }
}
