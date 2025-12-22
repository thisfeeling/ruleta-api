<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use App\Models\{Player, Achievement, PlayerAchievement};
use App\Events\Achievements\AchievementUnlocked;
use App\Events\Scoreboard\ScoreAdded;

class UpdatePlayerScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_player_score_and_updates_total()
    {
        Event::fake([ScoreAdded::class]);

        $player = Player::factory()->create(['total_score' => 0]);

        $achievement = Achievement::create([
            'key' => 'bonus_points',
            'name_es' => 'Bonus',
            'name_en' => 'Bonus',
            'description_es' => 'Bonus desc',
            'description_en' => 'Bonus desc',
            'points' => 25,
        ]);

        $playerAchievement = PlayerAchievement::create([
            'player_id' => $player->id,
            'achievement_id' => $achievement->id,
            'show_id' => $player->show_id,
            'unlocked_at' => now(),
        ]);

        event(new AchievementUnlocked($playerAchievement));

        $this->assertDatabaseHas('player_scores', [
            'player_id' => $player->id,
            'raw_score' => 25,
        ]);

        $player->refresh();
        $this->assertEquals(25, $player->total_score);

        Event::assertDispatched(ScoreAdded::class);
    }
}
