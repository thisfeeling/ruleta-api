<?php

namespace App\Events\Achievements;

use App\Models\PlayerAchievement;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AchievementUnlocked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PlayerAchievement $playerAchievement
    ) {}

    public function broadcastOn(): array
    {
        $player = $this->playerAchievement->player;

        return [
            new Channel("show.{$player->show_id}"),
            new Channel("player.{$player->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'achievement.unlocked';
    }

    public function broadcastWith(): array
    {
        $achievement = $this->playerAchievement->achievement;
        $player = $this->playerAchievement->player;

        return [
            'player_id' => $player->id,
            'player_number' => $player->player_number ?? null,
            'achievement' => [
                'id' => $achievement->id ?? null,
                'key' => $achievement->key ?? null,
                'name' => $achievement->name ?? null,
            ],
            'unlocked_at' => optional($this->playerAchievement->unlocked_at)?->toIso8601String(),
        ];
    }
}
