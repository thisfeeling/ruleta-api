<?php

namespace App\Services\Game;

use App\Models\{Game, Player, MillionaireQuestion, MillionaireAnswer};
use App\Services\Game\Contracts\GameServiceInterface;

class MillionaireService implements GameServiceInterface
{
    protected EliminationService $elimination;

    public function __construct(EliminationService $elimination)
    {
        $this->elimination = $elimination;
    }

    public function start(Game $game): void
    {
        $questions = $this->generateQuestions($game, 10);

        $game->update([
            'state' => [
                'current_question' => 0,
                'total_questions' => count($questions),
            ],
        ]);

        $this->displayNextQuestion($game);
    }

    public function handlePlayerAction(Game $game, Player $player, array $action): void
    {
        if (($action['type'] ?? '') === 'submit_answer') {
            $this->submitAnswer($game, $player, $action['answer'] ?? null);
        }
    }

    public function complete(Game $game): array
    {
        // Simple tally: count correct answers per player
        $results = MillionaireAnswer::whereHas('question', fn($q) => $q->where('game_id', $game->id))
            ->get()
            ->groupBy('player_id')
            ->map(fn($answers, $playerId) => [
                'player' => \App\Models\Player::find($playerId),
                'correct_count' => collect($answers)->where('is_correct', true)->count(),
                'total' => count($answers),
            ]);

        // Calculate elimination count (e.g., bottom 40%)
        $activeCount = $game->show->getActivePlayerCount();
        $eliminateCount = $this->elimination->calculateEliminationCount($activeCount, 'millionaire');

        $worst = $results->sortBy('correct_count')->take($eliminateCount)->pluck('player');

        $eliminated = $this->elimination->eliminatePlayers($game, $worst, 'incorrect_answers');

        return [
            'eliminated_count' => count($eliminated),
            'eliminated_players' => $eliminated,
            'results' => $results,
        ];
    }

    public function getState(Game $game): array
    {
        return $game->state ?? [];
    }

    protected function generateQuestions(Game $game, int $count): array
    {
        $questions = [];

        for ($i = 1; $i <= $count; $i++) {
            $questions[] = MillionaireQuestion::create([
                'game_id' => $game->id,
                'question_number' => $i,
                'question_text_es' => "Pregunta {$i} en español",
                'question_text_en' => "Question {$i} in English",
                'option_a_es' => 'Opción A',
                'option_a_en' => 'Option A',
                'option_b_es' => 'Opción B',
                'option_b_en' => 'Option B',
                'option_c_es' => 'Opción C',
                'option_c_en' => 'Option C',
                'option_d_es' => 'Opción D',
                'option_d_en' => 'Option D',
                'correct_answer' => ['A', 'B', 'C', 'D'][array_rand(['A','B','C','D'])],
                'time_limit_seconds' => 15,
            ]);
        }

        return $questions;
    }

    protected function displayNextQuestion(Game $game): void
    {
        $state = $game->state ?? [];
        $questionNumber = ($state['current_question'] ?? 0) + 1;

        $question = MillionaireQuestion::where('game_id', $game->id)
            ->where('question_number', $questionNumber)
            ->first();

        if ($question) {
            event(new \App\Events\Games\MillionaireQuestionDisplayed($game, $question));
        }
    }

    protected function submitAnswer(Game $game, Player $player, ?string $answer): void
    {
        $state = $game->state ?? [];
        $question = MillionaireQuestion::where('game_id', $game->id)
            ->where('question_number', ($state['current_question'] ?? 0) + 1)
            ->first();

        if (!$question || $answer === null) {
            return;
        }

        $isCorrect = $answer === $question->correct_answer;

        MillionaireAnswer::create([
            'question_id' => $question->id,
            'player_id' => $player->id,
            'selected_answer' => $answer,
            'is_correct' => $isCorrect,
            'time_taken_ms' => $state['time_elapsed'] ?? 0,
        ]);

        event(new \App\Events\Games\MillionaireAnswerSubmitted($game, $player, $question, $isCorrect));
    }
}
