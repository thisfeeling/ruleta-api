# 07 - Game Services Base

**Status**: [ ] Not Started

## Objetivo

Crear servicios base para manejo de juegos, state machine del show, y eliminación de jugadores.

## Dependencias

- **Anterior**: 06 - Authentication & Authorization

## Estructura

```
app/Services/Game/
├── GameEngineService.php         # Core game engine
├── ShowStateMachineService.php   # State transitions
├── EliminationService.php        # Player elimination logic
├── Contracts/
│   └── GameServiceInterface.php  # Interface for game implementations
```

## Implementación

### 7.1 Game Service Interface

```php
<?php

namespace App\Services\Game\Contracts;

use App\Models\{Game, Player};

interface GameServiceInterface
{
    public function start(Game $game): void;
    public function handlePlayerAction(Game $game, Player $player, array $action): void;
    public function complete(Game $game): array; // Returns elimination results
    public function getState(Game $game): array;
}
```

### 7.2 Show State Machine Service

```php
<?php

namespace App\Services\Game;

use App\Models\{Show, Game};
use App\Events\Show\PhaseChanged;

class ShowStateMachineService
{
    protected array $phases = [
        'lobby',
        'millionaire_1',
        'spell_1',
        'rope',
        'millionaire_2',
        'spell_2',
        'roulette',
        'results',
    ];

    public function transitionTo(Show $show, string $phase): void
    {
        $oldPhase = $show->current_phase;
        
        $show->update(['current_phase' => $phase]);
        
        event(new PhaseChanged($show, $oldPhase, $phase));
    }

    public function getNextPhase(Show $show): ?string
    {
        $currentIndex = array_search($show->current_phase, $this->phases);
        
        if ($currentIndex === false || $currentIndex >= count($this->phases) - 1) {
            return null;
        }
        
        return $this->phases[$currentIndex + 1];
    }

    public function canInsertBonusGame(Show $show): bool
    {
        // Bonus games can be inserted between main games
        return in_array($show->current_phase, [
            'millionaire_1',
            'spell_1',
            'rope',
            'millionaire_2',
        ]);
    }

    public function startBonusGame(Show $show, string $type): Game
    {
        return Game::create([
            'show_id' => $show->id,
            'type' => $type,
            'round_number' => 1,
            'status' => 'pending',
            'is_bonus' => true,
        ]);
    }
}
```

### 7.3 Elimination Service

```php
<?php

namespace App\Services\Game;

use App\Models\{Player, Game, Show};
use App\Events\Games\PlayerEliminated;
use Illuminate\Support\Collection;

class EliminationService
{
    /**
     * Eliminate players based on performance
     */
    public function eliminatePlayers(Game $game, Collection $players, string $reason): array
    {
        $eliminated = [];
        $show = $game->show;
        $nextOrder = $this->getNextEliminationOrder($show);
        
        foreach ($players as $player) {
            if ($player->status === 'active') {
                $player->eliminate($game->type, $nextOrder++);
                
                event(new PlayerEliminated($player, $game, $reason));
                
                $eliminated[] = $player->id;
            }
        }
        
        $game->increment('players_eliminated', count($eliminated));
        $show->decrement('current_player_count', count($eliminated));
        
        return $eliminated;
    }

    /**
     * Calculate dynamic elimination count
     */
    public function calculateEliminationCount(int $activeCount, string $gameType): int
    {
        return match($gameType) {
            'millionaire' => (int) ceil($activeCount * 0.4), // 40%
            'rope' => (int) ceil($activeCount * 0.25),       // 25%
            'spell' => (int) ceil($activeCount * 0.3),       // 30%
            'roulette' => $activeCount - 1,                  // All but 1
            default => 0,
        };
    }

    /**
     * Get worst performing players
     */
    public function getWorstPerformers(Collection $players, int $count): Collection
    {
        return $players->sortBy('total_score')->take($count);
    }

    protected function getNextEliminationOrder(Show $show): int
    {
        return Player::where('show_id', $show->id)
            ->whereNotNull('elimination_order')
            ->max('elimination_order') + 1;
    }
}
```

### 7.4 Game Engine Service

```php
<?php

namespace App\Services\Game;

use App\Models\{Show, Game};
use App\Events\Games\{GameStarted, GameEnded};
use App\Services\Game\Contracts\GameServiceInterface;

class GameEngineService
{
    protected array $gameServices = [];

    public function registerGameService(string $gameType, GameServiceInterface $service): void
    {
        $this->gameServices[$gameType] = $service;
    }

    public function getGameService(string $gameType): GameServiceInterface
    {
        if (!isset($this->gameServices[$gameType])) {
            throw new \Exception("Game service not found for type: {$gameType}");
        }
        
        return $this->gameServices[$gameType];
    }

    public function startGame(Game $game): void
    {
        $game->start();
        
        $service = $this->getGameService($game->type);
        $service->start($game);
        
        event(new GameStarted($game));
    }

    public function completeGame(Game $game): void
    {
        $service = $this->getGameService($game->type);
        $results = $service->complete($game);
        
        $game->complete();
        
        event(new GameEnded($game, $results));
    }
}
```

### 7.5 Service Provider

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Game\{
    GameEngineService,
    ShowStateMachineService,
    EliminationService
};

class GameServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GameEngineService::class);
        $this->app->singleton(ShowStateMachineService::class);
        $this->app->singleton(EliminationService::class);
    }

    public function boot(): void
    {
        $engine = $this->app->make(GameEngineService::class);
        
        // Register game implementations (created in next steps)
        // $engine->registerGameService('millionaire', $this->app->make(MillionaireService::class));
        // $engine->registerGameService('rope', $this->app->make(RopeService::class));
        // etc...
    }
}
```

Register in `config/app.php`:

```php
'providers' => ServiceProvider::defaultProviders()->merge([
    App\Providers\GameServiceProvider::class,
])->toArray(),
```

## Próximos Pasos

→ **08-13**: Implementar servicios específicos de cada juego
