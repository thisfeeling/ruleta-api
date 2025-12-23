<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Models\{User, Show, Game, Player, GameInstruction, InstructionRead};

class InstructionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_as_read_endpoint_marks_read()
    {
        $user = User::factory()->create(['role' => 'player']);
        $show = Show::factory()->create();
        $player = Player::factory()->create(['user_id' => $user->id, 'show_id' => $show->id]);
        $game = Game::factory()->create(['show_id' => $show->id, 'type' => 'millionaire']);
        $instruction = GameInstruction::create([
            'game_type' => 'millionaire',
            'content_es' => 'Haz esto',
            'content_en' => 'Do this',
            'estimated_duration_seconds' => 10,
        ]);

        // Create an InstructionRead entry
        InstructionRead::create([
            'game_id' => $game->id,
            'player_id' => $player->id,
            'instruction_id' => $instruction->id,
            'started_at' => now(),
            'completed' => false,
        ]);

        $this->actingAs($user)->postJson("/api/games/{$game->id}/instructions/read")
            ->assertStatus(200)
            ->assertJson(['message' => 'Instructions marked as read']);

        $this->assertDatabaseHas('instruction_reads', [
            'game_id' => $game->id,
            'player_id' => $player->id,
            'completed' => true,
        ]);
    }

    public function test_force_complete_requires_supervisor()
    {
        $user = User::factory()->create(['role' => 'player']);
        $show = Show::factory()->create();
        $game = Game::factory()->create(['show_id' => $show->id]);

        $this->actingAs($user)->postJson("/api/games/{$game->id}/instructions/force-complete")
            ->assertStatus(403);

        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $this->actingAs($supervisor)->postJson("/api/games/{$game->id}/instructions/force-complete")
            ->assertStatus(200)
            ->assertJson(['message' => 'All instructions marked as completed']);
    }
}
