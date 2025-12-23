<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, Game, Player};
use Illuminate\Foundation\Testing\RefreshDatabase;

class GameControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_endpoint_returns_state()
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['type' => 'millionaire', 'state' => ['current_question' => 0]]);

        // create player for user
        $player = Player::factory()->create(['user_id' => $user->id, 'show_id' => $game->show_id, 'status' => 'active']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/games/' . $game->id . '/state')
            ->assertStatus(200)
            ->assertJsonStructure(['state']);
    }

    public function test_action_endpoint_accepts_action()
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['type' => 'word_search']);
        $player = Player::factory()->create(['user_id' => $user->id, 'show_id' => $game->show_id, 'status' => 'active']);

        $payload = ['action' => ['type' => 'found_word', 'word' => 'FAMILIA', 'time_elapsed_ms' => 1000]];

        // Ensure grid exists for the word search game since RecordFind depends on it
        \App\Models\WordSearchGrid::create([ 'game_id' => $game->id, 'grid' => array_fill(0,15, array_fill(0,15,'')), 'words' => ['FAMILIA'], 'time_limit_seconds' => 180 ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games/' . $game->id . '/action', $payload)
            ->assertStatus(200)
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('word_search_finds', ['player_id' => $player->id, 'word' => 'FAMILIA']);
    }
}
