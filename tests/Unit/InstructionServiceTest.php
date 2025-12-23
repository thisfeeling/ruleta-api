<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

use App\Models\{Show, Game, Player, GameInstruction, InstructionRead};
use App\Services\Instruction\InstructionService;
use App\Events\Instructions\InstructionsCompleted;

class InstructionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_require_instructions_creates_reads_and_updates_game_status()
    {
        $show = Show::factory()->create();

        $players = Player::factory()->count(3)->create(['show_id' => $show->id, 'status' => 'active']);

        $game = Game::factory()->create(['show_id' => $show->id, 'type' => 'millionaire']);

        $instruction = GameInstruction::create([
            'game_type' => 'millionaire',
            'content_es' => 'Haz esto',
            'content_en' => 'Do this',
            'estimated_duration_seconds' => 10,
        ]);

        $service = new InstructionService();
        $service->requireInstructions($game);

        $this->assertEquals('instructions', $game->fresh()->status);

        foreach ($players as $player) {
            $this->assertDatabaseHas('instruction_reads', [
                'game_id' => $game->id,
                'player_id' => $player->id,
                'instruction_id' => $instruction->id,
                'completed' => false,
            ]);
        }
    }

    public function test_mark_as_read_marks_completed_and_triggers_completed_event_when_all_read()
    {
        Event::fake();

        $show = Show::factory()->create();
        $players = Player::factory()->count(2)->create(['show_id' => $show->id, 'status' => 'active']);
        $game = Game::factory()->create(['show_id' => $show->id, 'type' => 'millionaire']);
        $instruction = GameInstruction::create([
            'game_type' => 'millionaire',
            'content_es' => 'Haz esto',
            'content_en' => 'Do this',
            'estimated_duration_seconds' => 10,
        ]);

        foreach ($players as $player) {
            InstructionRead::create([
                'game_id' => $game->id,
                'player_id' => $player->id,
                'instruction_id' => $instruction->id,
                'started_at' => now(),
                'completed' => false,
            ]);
        }

        $service = new InstructionService();

        // Mark first player
        $service->markAsRead($game, $players[0]);
        $this->assertDatabaseHas('instruction_reads', [
            'game_id' => $game->id,
            'player_id' => $players[0]->id,
            'completed' => true,
        ]);

        // Mark second player, should trigger InstructionsCompleted
        $service->markAsRead($game, $players[1]);

        Event::assertDispatched(InstructionsCompleted::class);
    }
}
