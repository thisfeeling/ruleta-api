<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, Player, Achievement, PlayerAchievement};

class AchievementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_all_achievements_requires_auth_and_returns_list()
    {
        $user = User::factory()->create();

        Achievement::create([
            'key' => 'a1',
            'name_es' => 'A1',
            'name_en' => 'A1',
            'description_es' => 'Desc',
            'description_en' => 'Desc',
            'points' => 0,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/achievements')
            ->assertStatus(200)
            ->assertJsonCount(1);
    }

    public function test_get_my_achievements_returns_player_data()
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['user_id' => $user->id]);

        $achievement = Achievement::create([
            'key' => 'a2',
            'name_es' => 'A2',
            'name_en' => 'A2',
            'description_es' => 'Desc',
            'description_en' => 'Desc',
            'points' => 0,
        ]);

        PlayerAchievement::create(['player_id' => $player->id, 'achievement_id' => $achievement->id, 'show_id' => $player->show_id, 'unlocked_at' => now()]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/achievements/me')
            ->assertStatus(200)
            ->assertJsonStructure(['achievements', 'progress']);
    }
}
