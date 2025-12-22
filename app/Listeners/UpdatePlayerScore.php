<?php

namespace App\Listeners;

use App\Events\Achievements\AchievementUnlocked;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class UpdatePlayerScore implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(AchievementUnlocked $event): void
    {
        $playerAchievement = $event->playerAchievement;
        $player = $playerAchievement->player;
        $achievement = $playerAchievement->achievement;

        $points = (int) ($achievement->points ?? 0);
        $normalized = min(1000, $points);

        try {
            \DB::transaction(function () use ($player, $playerAchievement, $points, $normalized) {
                // Create a player score record
                $score = $player->addScore(
                    null,
                    'achievement',
                    $points,
                    $normalized,
                    [
                        'achievement_id' => $playerAchievement->achievement_id,
                    ]
                );

                // Update player total
                $player->increment('total_score', $points);

                // Dispatch scoreboard event
                event(new \App\Events\Scoreboard\ScoreAdded($score));
            });
        } catch (\Throwable $e) {
            Log::error('Failed to update player score', [
                'player_id' => $player->id,
                'achievement_id' => $playerAchievement->achievement_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
