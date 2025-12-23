<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Game, Player, WordSearchGrid};
use App\Events\Games\{MillionaireQuestionDisplayed, MillionaireAnswerSubmitted, WordSearchGridGenerated, WordFound};
use Illuminate\Broadcasting\PrivateChannel;

class GameEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_millionaire_question_displayed_broadcast_channel()
    {
        $game = Game::factory()->create(['type' => 'millionaire']);
        $question = \App\Models\MillionaireQuestion::create([
            'game_id' => $game->id,
            'question_number' => 1,
            'question_text_es' => '¿x?',
            'question_text_en' => 'x?',
            'option_a_es' => 'A', 'option_a_en' => 'A',
            'option_b_es' => 'B', 'option_b_en' => 'B',
            'option_c_es' => 'C', 'option_c_en' => 'C',
            'option_d_es' => 'D', 'option_d_en' => 'D',
            'correct_answer' => 'A',
            'time_limit_seconds' => 10,
        ]);

        $event = new MillionaireQuestionDisplayed($game, $question);
        $channel = $event->broadcastOn();
        $this->assertInstanceOf(PrivateChannel::class, $channel);
    }

    public function test_word_found_broadcast_payload()
    {
        $game = Game::factory()->create(['type' => 'word_search']);
        $player = Player::factory()->create(['show_id' => $game->show_id, 'status' => 'active']);
        $grid = WordSearchGrid::create(['game_id' => $game->id, 'grid' => array_fill(0,15, array_fill(0,15,'')), 'words' => ['FAMILIA'], 'time_limit_seconds' => 180]);

        $event = new WordFound($game, $player, $grid, 'FAMILIA', 1);
        $payload = $event->broadcastWith();

        $this->assertEquals($payload['game_id'], $game->id);
        $this->assertEquals($payload['player_id'], $player->id);
        $this->assertEquals($payload['word'], 'FAMILIA');
    }
}
