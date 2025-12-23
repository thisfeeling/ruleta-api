<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, Show, Player, Game};

class ScoreboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoreboard_endpoints_require_auth_and_return_data()
    {
        $user = User::factory()->create();
        $show = Show::factory()->create();

        $player = Player::factory()->create(['user_id' => $user->id, 'show_id' => $show->id]);
        $game = Game::factory()->create(['show_id' => $show->id, 'type' => 'flappy']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/shows/{$show->id}/scoreboard")
            ->assertStatus(200)
            ->assertJsonStructure(['scoreboard', 'updated_at']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/shows/{$show->id}/scoreboard/top?limit=5")
            ->assertStatus(200)
            ->assertJsonStructure(['top_players']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/scoreboard/me')
            ->assertStatus(200)
            ->assertJsonStructure(['rank', 'total_score', 'scores']);
    }
}
