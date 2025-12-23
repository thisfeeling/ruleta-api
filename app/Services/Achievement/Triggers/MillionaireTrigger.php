<?php

namespace App\Services\Achievement\Triggers;

use App\Models\{Player, MillionaireAnswer};
use App\Services\Achievement\AchievementService;

class MillionaireTrigger
{
    protected AchievementService $achievements;

    public function __construct(AchievementService $achievements)
    {
        $this->achievements = $achievements;
    }

    public function checkPerfectGame(Player $player, int $gameId): void
    {
        $answers = MillionaireAnswer::whereHas('question', fn($q) => $q->where('game_id', $gameId))
            ->where('player_id', $player->id)
            ->get();

        if ($answers->isNotEmpty() && $answers->every(fn($a) => $a->is_correct)) {
            $this->achievements->unlock($player, 'millionaire_perfect', [
                'game_id' => $gameId,
                'total_questions' => $answers->count(),
            ]);
        }
    }

    public function checkSpeedDemon(Player $player, MillionaireAnswer $answer): void
    {
        if ($answer->time_taken_ms < 3000) {
            // Check last 5 answers
            $recentFast = MillionaireAnswer::where('player_id', $player->id)
                ->where('time_taken_ms', '<', 3000)
                ->orderByDesc('created_at')
                ->limit(5)
                ->count();

            if ($recentFast >= 5) {
                $this->achievements->unlock($player, 'millionaire_speed_demon');
            }
        }
    }
}
