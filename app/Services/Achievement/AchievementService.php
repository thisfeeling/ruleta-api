<?php

namespace App\Services\Achievement;

use App\Models\{Player, Achievement, PlayerAchievement, AchievementProgress, AuditLog};
use App\Events\Achievements\AchievementUnlocked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class AchievementService
{
    public function unlock(Player $player, string $achievementKey, ?array $metadata = null): ?PlayerAchievement
    {
        $achievement = Achievement::where('key', $achievementKey)->first();

        if (!$achievement) {
            return null;
        }

        // Check if already unlocked
        $existing = PlayerAchievement::where('player_id', $player->id)
            ->where('achievement_id', $achievement->id)
            ->where('show_id', $player->show_id)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Create unlock record inside a transaction
        $playerAchievement = DB::transaction(function () use ($player, $achievement, $metadata) {
            $pa = PlayerAchievement::create([
                'player_id' => $player->id,
                'achievement_id' => $achievement->id,
                'show_id' => $player->show_id,
                    'unlocked_at' => now(),
                'context' => $metadata,
            ]);

            // Audit
            try {
                AuditLog::create([
                    'show_id' => $player->show_id,
                    'event_type' => 'achievement_unlocked',
                    'payload' => [
                        'player_id' => $player->id,
                        'achievement_id' => $achievement->id,
                        'metadata' => $metadata,
                    ],
                ]);
            } catch (\Throwable $e) {
                // Do not block unlock on audit failures
            }

            return $pa;
        });

        // Broadcast event (UpdatePlayerScore listener will handle player score)
        event(new AchievementUnlocked($playerAchievement));

        return $playerAchievement;
    }

    public function updateProgress(Player $player, string $achievementKey, int $progress, int $required): ?PlayerAchievement
    {
        $achievement = Achievement::where('key', $achievementKey)->first();

        if (!$achievement) {
            return null;
        }

        AchievementProgress::updateOrCreate(
            [
                'player_id' => $player->id,
                'achievement_id' => $achievement->id,
            ],
            [
                'current_progress' => $progress,
                'required_progress' => $required,
                'metadata' => ['required' => $required],
            ]
        );

        // Check if completed
        if ($progress >= $required) {
            return $this->unlock($player, $achievementKey, ['progress' => $progress]);
        }

        return null;
    }

    public function getPlayerAchievements(Player $player): Collection
    {
        return PlayerAchievement::where('player_id', $player->id)
            ->with('achievement')
            ->get();
    }

    public function getProgress(Player $player): Collection
    {
        return AchievementProgress::where('player_id', $player->id)
            ->with('achievement')
            ->get();
    }
}
