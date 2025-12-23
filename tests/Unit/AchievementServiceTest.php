<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use App\Models\{Player, Achievement, PlayerAchievement};
use App\Events\Achievements\AchievementUnlocked;

class AchievementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unlock_creates_player_achievement_and_dispatches_event()
    {
        Event::fake([AchievementUnlocked::class]);

        $player = Player::factory()->create();

        $achievement = Achievement::create([
            'key' => 'test_bonus',
            'name_es' => 'Test',
            'name_en' => 'Test',
            'description_es' => 'Desc',
            'description_en' => 'Desc',
            'points' => 5,
        ]);

        $service = app(\App\Services\Achievement\AchievementService::class);

        $pa = $service->unlock($player, 'test_bonus', ['reason' => 'unit_test']);

        $this->assertInstanceOf(PlayerAchievement::class, $pa);
        $this->assertDatabaseHas('player_achievements', [
            'player_id' => $player->id,
            'achievement_id' => $achievement->id,
        ]);

        Event::assertDispatched(AchievementUnlocked::class);
    }

    public function test_update_progress_unlocks_when_required_reached()
    {
        Event::fake([AchievementUnlocked::class]);

        $player = Player::factory()->create();

        $achievement = Achievement::create([
            'key' => 'progress_test',
            'name_es' => 'Progress',
            'name_en' => 'Progress',
            'description_es' => 'Desc',
            'description_en' => 'Desc',
            'points' => 10,
        ]);

        $service = app(\App\Services\Achievement\AchievementService::class);

        $service->updateProgress($player, 'progress_test', 3, 5);

        $this->assertDatabaseHas('achievement_progress', [
            'player_id' => $player->id,
            'achievement_id' => $achievement->id,
        ]);

        // not unlocked yet
        $this->assertDatabaseMissing('player_achievements', [
            'player_id' => $player->id,
            'achievement_id' => $achievement->id,
        ]);

        // reach required
        $service->updateProgress($player, 'progress_test', 5, 5);

        $this->assertDatabaseHas('player_achievements', [
            'player_id' => $player->id,
            'achievement_id' => $achievement->id,
        ]);

        Event::assertDispatched(AchievementUnlocked::class);
    }
}
