# 08-13 - All Game Implementations

**Status**: [x] Completed

## Objetivo

Implementar servicios, controllers y lógica para todos los 6 juegos (4 main + 2 bonus).

## Juegos

1. **Millionaire** - Preguntas y respuestas
2. **Rope** - Agrupamiento, votación y batalla de clicks
3. **Spell** - Deletreo con audio
4. **Roulette** - Ruleta de puntos (final)
5. **Word Search** - Sopa de letras (bonus)
6. **Flappy** - Juego de supervivencia (bonus)

## 08 - Millionaire Game

### Service

```php
<?php

namespace App\Services\Game;

use App\Models\{Game, Player, MillionaireQuestion, MillionaireAnswer};
use App\Services\Game\Contracts\GameServiceInterface;
use App\Events\Games\Millionaire\{QuestionDisplayed, AnswerSubmitted, RoundCompleted};

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
        if ($action['type'] === 'submit_answer') {
            $this->submitAnswer($game, $player, $action['answer']);
        }
    }

    public function complete(Game $game): array
    {
        $results = $this->calculateResults($game);
        
        // Eliminate bottom 40%
        $activeCount = $game->show->getActivePlayerCount();
        $eliminateCount = $this->elimination->calculateEliminationCount($activeCount, 'millionaire');
        
        $worstPerformers = $results->sortBy('correct_count')->take($eliminateCount);
        
        $eliminated = $this->elimination->eliminatePlayers(
            $game,
            $worstPerformers->pluck('player'),
            'incorrect_answers'
        );
        
        return [
            'eliminated_count' => count($eliminated),
            'eliminated_players' => $eliminated,
            'results' => $results,
        ];
    }

    public function getState(Game $game): array
    {
        return $game->state;
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
                'correct_answer' => ['A', 'B', 'C', 'D'][rand(0, 3)],
                'time_limit_seconds' => 15,
            ]);
        }
        
        return $questions;
    }

    protected function displayNextQuestion(Game $game): void
    {
        $state = $game->state;
        $questionNumber = $state['current_question'] + 1;
        
        $question = MillionaireQuestion::where('game_id', $game->id)
            ->where('question_number', $questionNumber)
            ->first();
        
        if ($question) {
            event(new QuestionDisplayed($game, $question));
        }
    }

    protected function submitAnswer(Game $game, Player $player, string $answer): void
    {
        $state = $game->state;
        $question = MillionaireQuestion::where('game_id', $game->id)
            ->where('question_number', $state['current_question'] + 1)
            ->first();
        
        $isCorrect = $answer === $question->correct_answer;
        
        MillionaireAnswer::create([
            'question_id' => $question->id,
            'player_id' => $player->id,
            'selected_answer' => $answer,
            'is_correct' => $isCorrect,
            'time_taken_ms' => $state['time_elapsed'] ?? 0,
        ]);
        
        event(new AnswerSubmitted($game, $player, $question, $isCorrect));
    }
}
```

## 09 - Rope Game

```php
<?php

namespace App\Services\Game;

use App\Models\{Game, Player, RopeGroup};
use App\Services\Game\Contracts\GameServiceInterface;

class RopeService implements GameServiceInterface
{
    protected EliminationService $elimination;

    public function __construct(EliminationService $elimination)
    {
        $this->elimination = $elimination;
    }

    public function start(Game $game): void
    {
        $groups = $this->createGroups($game);
        
        $game->update([
            'state' => [
                'phase' => 'voting',
                'groups' => $groups->pluck('id'),
            ],
        ]);
    }

    public function handlePlayerAction(Game $game, Player $player, array $action): void
    {
        match($action['type']) {
            'cast_vote' => $this->castVote($game, $player, $action['voted_for_id']),
            'click' => $this->recordClick($game, $player),
            default => null,
        };
    }

    public function complete(Game $game): array
    {
        // Losing groups get eliminated
        $losingGroups = RopeGroup::where('game_id', $game->id)
            ->where('status', 'eliminated')
            ->get();
        
        $eliminatedPlayers = [];
        
        foreach ($losingGroups as $group) {
            $players = $group->players;
            $eliminated = $this->elimination->eliminatePlayers($game, $players, 'lost_rope_battle');
            $eliminatedPlayers = array_merge($eliminatedPlayers, $eliminated);
        }
        
        return [
            'eliminated_count' => count($eliminatedPlayers),
            'eliminated_players' => $eliminatedPlayers,
        ];
    }

    public function getState(Game $game): array
    {
        return $game->state;
    }

    protected function createGroups(Game $game): \Illuminate\Support\Collection
    {
        $activePlayers = Player::where('show_id', $game->show_id)
            ->where('status', 'active')
            ->get();
        
        $groupCount = (int) ceil($activePlayers->count() / 5);
        $groups = collect();
        
        $shuffled = $activePlayers->shuffle();
        
        foreach ($shuffled->chunk($groupCount) as $index => $chunk) {
            $group = RopeGroup::create([
                'game_id' => $game->id,
                'group_name' => 'Grupo ' . chr(65 + $index), // A, B, C...
                'members_count' => $chunk->count(),
                'status' => 'voting',
            ]);
            
            foreach ($chunk as $player) {
                $group->players()->attach($player->id);
            }
            
            $groups->push($group);
        }
        
        return $groups;
    }

    protected function castVote(Game $game, Player $voter, int $votedForId): void
    {
        $group = RopeGroup::whereHas('players', fn($q) => $q->where('player_id', $voter->id))
            ->where('game_id', $game->id)
            ->first();
        
        if ($group) {
            \App\Models\RopeVote::create([
                'group_id' => $group->id,
                'voter_id' => $voter->id,
                'voted_for_id' => $votedForId,
            ]);
        }
    }

    protected function recordClick(Game $game, Player $player): void
    {
        $group = RopeGroup::whereHas('players', fn($q) => $q->where('player_id', $player->id))
            ->where('game_id', $game->id)
            ->first();
        
        if ($group) {
            $group->increment('total_clicks');
            $group->players()->updateExistingPivot($player->id, [
                'clicks_contributed' => \DB::raw('clicks_contributed + 1'),
            ]);
        }
    }
}
```

## 10 - Spell Game

```php
<?php

namespace App\Services\Game;

use App\Models\{Game, Player, SpellWord};
use App\Services\Game\Contracts\GameServiceInterface;

class SpellService implements GameServiceInterface
{
    public function start(Game $game): void
    {
        $this->assignWords($game);
    }

    public function handlePlayerAction(Game $game, Player $player, array $action): void
    {
        if ($action['type'] === 'submit_spelling') {
            $this->submitSpelling($game, $player, $action['spelling']);
        }
    }

    public function complete(Game $game): array
    {
        $incorrect = SpellWord::where('game_id', $game->id)
            ->whereHas('attempts', fn($q) => $q->where('is_correct', false))
            ->with('player')
            ->get();
        
        $eliminated = app(EliminationService::class)->eliminatePlayers(
            $game,
            $incorrect->pluck('player'),
            'incorrect_spelling'
        );
        
        return ['eliminated_count' => count($eliminated), 'eliminated_players' => $eliminated];
    }

    public function getState(Game $game): array
    {
        return $game->state;
    }

    protected function assignWords(Game $game): void
    {
        $players = Player::where('show_id', $game->show_id)->where('status', 'active')->get();
        
        foreach ($players as $player) {
            SpellWord::create([
                'game_id' => $game->id,
                'player_id' => $player->id,
                'word' => $this->getRandomWord(),
                'difficulty' => 'medium',
                'time_limit_seconds' => 60,
            ]);
        }
    }

    protected function getRandomWord(): string
    {
        $words = ['mariposa', 'exuberante', 'orquesta', 'paradigma'];
        return $words[array_rand($words)];
    }

    protected function submitSpelling(Game $game, Player $player, string $spelling): void
    {
        $word = SpellWord::where('game_id', $game->id)
            ->where('player_id', $player->id)
            ->first();
        
        if ($word) {
            \App\Models\SpellAttempt::create([
                'spell_word_id' => $word->id,
                'submitted_spelling' => $spelling,
                'is_correct' => strtolower($spelling) === strtolower($word->word),
                'time_taken_ms' => 0,
            ]);
        }
    }
}
```

## 11 - Roulette Game

```php
<?php

namespace App\Services\Game;

use App\Models\{Game, Player, RouletteSpin};
use App\Services\Game\Contracts\GameServiceInterface;

class RouletteService implements GameServiceInterface
{
    public function start(Game $game): void
    {
        $game->update(['state' => ['round' => 1, 'active_players' => []]]);
    }

    public function handlePlayerAction(Game $game, Player $player, array $action): void
    {
        if ($action['type'] === 'spin') {
            $this->recordSpin($game, $player);
        }
    }

    public function complete(Game $game): array
    {
        // Find lowest scorer
        $lowestScore = RouletteSpin::where('game_id', $game->id)
            ->selectRaw('player_id, SUM(points_won) as total')
            ->groupBy('player_id')
            ->orderBy('total')
            ->first();
        
        if ($lowestScore) {
            $player = Player::find($lowestScore->player_id);
            $eliminated = app(EliminationService::class)->eliminatePlayers(
                $game,
                collect([$player]),
                'lowest_roulette_score'
            );
            
            return ['eliminated_count' => 1, 'eliminated_players' => $eliminated];
        }
        
        return ['eliminated_count' => 0, 'eliminated_players' => []];
    }

    public function getState(Game $game): array
    {
        return $game->state;
    }

    protected function recordSpin(Game $game, Player $player): void
    {
        $points = rand(0, 100) * 10; // 0-1000
        
        RouletteSpin::create([
            'game_id' => $game->id,
            'player_id' => $player->id,
            'round_number' => $game->state['round'],
            'points_won' => $points,
            'cumulative_score' => $this->getCumulativeScore($game, $player) + $points,
            'spin_duration_seconds' => 3.5,
        ]);
    }

    protected function getCumulativeScore(Game $game, Player $player): int
    {
        return RouletteSpin::where('game_id', $game->id)
            ->where('player_id', $player->id)
            ->sum('points_won');
    }
}
```

## 12 - Word Search Game (Bonus)

```php
<?php

namespace App\Services\Game;

use App\Models\{Game, Player, WordSearchGrid, WordSearchFind};
use App\Services\Game\Contracts\GameServiceInterface;

class WordSearchService implements GameServiceInterface
{
    public function start(Game $game): void
    {
        $grid = $this->generateGrid($game);
        
        $game->update(['state' => ['grid_id' => $grid->id]]);
    }

    public function handlePlayerAction(Game $game, Player $player, array $action): void
    {
        if ($action['type'] === 'found_word') {
            $this->recordFind($game, $player, $action['word'], $action['time_elapsed_ms']);
        }
    }

    public function complete(Game $game): array
    {
        // NO eliminations in bonus games, just scores
        return ['eliminated_count' => 0, 'eliminated_players' => []];
    }

    public function getState(Game $game): array
    {
        return $game->state;
    }

    protected function generateGrid(Game $game): WordSearchGrid
    {
        $grid = array_fill(0, 15, array_fill(0, 15, ''));
        $words = ['FAMILIA', 'JUEGO', 'RULETA', 'PREMIO'];
        
        // Simple placement logic (would be more complex in reality)
        
        return WordSearchGrid::create([
            'game_id' => $game->id,
            'grid' => $grid,
            'words' => $words,
            'time_limit_seconds' => 180,
        ]);
    }

    protected function recordFind(Game $game, Player $player, string $word, int $timeMs): void
    {
        $grid = WordSearchGrid::where('game_id', $game->id)->first();
        
        if ($grid) {
            $findOrder = WordSearchFind::where('grid_id', $grid->id)->count() + 1;
            
            WordSearchFind::create([
                'grid_id' => $grid->id,
                'player_id' => $player->id,
                'word' => $word,
                'find_order' => $findOrder,
                'time_elapsed_ms' => $timeMs,
            ]);
        }
    }
}
```

## 13 - Flappy Game (Bonus)

```php
<?php

namespace App\Services\Game;

use App\Models\{Game, Player, FlappyAttempt};
use App\Services\Game\Contracts\GameServiceInterface;

class FlappyService implements GameServiceInterface
{
    public function start(Game $game): void
    {
        $game->update(['state' => ['active' => true]]);
    }

    public function handlePlayerAction(Game $game, Player $player, array $action): void
    {
        if ($action['type'] === 'crashed') {
            $this->recordAttempt($game, $player, $action);
        }
    }

    public function complete(Game $game): array
    {
        // NO eliminations in bonus games
        return ['eliminated_count' => 0, 'eliminated_players' => []];
    }

    public function getState(Game $game): array
    {
        return $game->state;
    }

    protected function recordAttempt(Game $game, Player $player, array $data): void
    {
        FlappyAttempt::create([
            'game_id' => $game->id,
            'player_id' => $player->id,
            'survival_time_ms' => $data['survival_time_ms'],
            'pipes_passed' => $data['pipes_passed'] ?? 0,
            'score' => $data['score'] ?? 0,
        ]);
    }
}
```

## Controllers

Create controllers for each game to handle HTTP endpoints:

```bash
php artisan make:controller Game/MillionaireController
php artisan make:controller Game/RopeController
# etc...
```

Each controller exposes:
- `POST /games/{game}/action` - Player actions
- `GET /games/{game}/state` - Current state
- `POST /games/{game}/complete` - Complete game (supervisor only)

## Próximos Pasos

→ **14 - Achievement System**: Triggers y unlocking
