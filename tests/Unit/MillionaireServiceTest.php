<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Game, Player, MillionaireQuestion};
use Illuminate\Support\Facades\Event;

class MillionaireServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_display_next_question_dispatches_event()
    {
        Event::fake();

        $game = Game::factory()->create(['type' => 'millionaire', 'state' => ['current_question' => 0]]);

        // create a question
        $question = MillionaireQuestion::create([
            'game_id' => $game->id,
            'question_number' => 1,
            'question_text_es' => '¿Test?',
            'question_text_en' => 'Test?',
            'option_a_es' => 'A',
            'option_a_en' => 'A',
            'option_b_es' => 'B',
            'option_b_en' => 'B',
            'option_c_es' => 'C',
            'option_c_en' => 'C',
            'option_d_es' => 'D',
            'option_d_en' => 'D',
            'correct_answer' => 'A',
            'time_limit_seconds' => 15,
        ]);

        app(\App\Services\Game\MillionaireService::class)->start($game);

        Event::assertDispatched(\App\Events\Games\MillionaireQuestionDisplayed::class, function ($event) use ($game, $question) {
            return $event->game->id === $game->id && $event->question->id === $question->id;
        });
    }

    public function test_submit_answer_dispatches_event()
    {
        Event::fake();

        $game = Game::factory()->create(['type' => 'millionaire', 'state' => ['current_question' => 0]]);
        $player = Player::factory()->create(['show_id' => $game->show_id, 'status' => 'active']);
        $question = MillionaireQuestion::create([
            'game_id' => $game->id,
            'question_number' => 1,
            'question_text_es' => '¿Test?',
            'question_text_en' => 'Test?',
            'option_a_es' => 'A',
            'option_a_en' => 'A',
            'option_b_es' => 'B',
            'option_b_en' => 'B',
            'option_c_es' => 'C',
            'option_c_en' => 'C',
            'option_d_es' => 'D',
            'option_d_en' => 'D',
            'correct_answer' => 'A',
            'time_limit_seconds' => 15,
        ]);

        // advance state so submitAnswer picks question 1
        $game->update(['state' => ['current_question' => 0]]);

        app(\App\Services\Game\MillionaireService::class)->handlePlayerAction($game, $player, ['type' => 'submit_answer', 'answer' => 'A']);

        Event::assertDispatched(\App\Events\Games\MillionaireAnswerSubmitted::class, function ($event) use ($player, $question) {
            return $event->player->id === $player->id && $event->question->id === $question->id && $event->isCorrect === true;
        });
    }
}
