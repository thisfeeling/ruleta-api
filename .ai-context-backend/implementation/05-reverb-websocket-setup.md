# 05 - Reverb WebSocket Setup

**Status**: [x] Completed

## Objetivo

Configurar Laravel Reverb, crear canales, eventos WebSocket y broadcasting de estados en tiempo real.

## Dependencias

- **Anterior**: 04 - Core Services
- **Sincronización Frontend**: El frontend consume estos eventos via Laravel Echo

## WebSocket Events (28+)

Ver `reverb-websockets.md` para lista completa de eventos.

## Implementación

### 5.1 Install Reverb

```bash
# Already installed in step 01
php artisan reverb:install
```

Verify `config/broadcasting.php` has Reverb driver.

### 5.2 Broadcasting Channels

Editar `routes/channels.php`:

```php
<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\{Show, Player, User};

// Public channel - show lobby
Broadcast::channel('show.{showId}', function (User $user, int $showId) {
    return true; // Public access
});

// Player channel - private player data
Broadcast::channel('player.{playerId}', function (User $user, int $playerId) {
    return $user->players()->where('id', $playerId)->exists();
});

// Supervisor channel - admin controls
Broadcast::channel('supervisor.{showId}', function (User $user, int $showId) {
    return $user->isSupervisor();
});

// Game channel - specific game events
Broadcast::channel('game.{gameId}', function (User $user, int $gameId) {
    // Check if user is participating in this game's show
    $game = \App\Models\Game::find($gameId);
    if (!$game) return false;
    
    return $user->players()->where('show_id', $game->show_id)->exists()
        || $user->isSupervisor();
});

// Presence channel - who's online
Broadcast::channel('presence.show.{showId}', function (User $user, int $showId) {
    $player = $user->players()->where('show_id', $showId)->first();
    
    if ($player) {
        return [
            'id' => $user->id,
            'player_id' => $player->id,
            'player_number' => $player->player_number,
            'name' => $user->name,
            'role' => $user->role,
        ];
    }
    
    return false;
});
```

### 5.3 Base Event Class

Crear trait para eventos WebSocket:

```bash
mkdir -p app/Events/Traits
touch app/Events/Traits/BroadcastsToShow.php
```

```php
<?php

namespace App\Events\Traits;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

trait BroadcastsToShow
{
    public function broadcastOn(): array
    {
        return [
            new Channel("show.{$this->show->id}"),
        ];
    }
}
```

### 5.4 Show Events

```bash
mkdir -p app/Events/Show
```

**ShowStarted Event**:

```php
<?php

namespace App\Events\Show;

use App\Models\Show;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShowStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Show $show
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("show.{$this->show->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'show.started';
    }

    public function broadcastWith(): array
    {
        return [
            'show_id' => $this->show->id,
            'started_at' => $this->show->started_at->toIso8601String(),
            'current_phase' => $this->show->current_phase,
            'player_count' => $this->show->current_player_count,
        ];
    }
}
```

**PhaseChanged Event**:

```php
<?php

namespace App\Events\Show;

use App\Models\Show;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PhaseChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Show $show,
        public string $fromPhase,
        public string $toPhase
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("show.{$this->show->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'show.phase_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'show_id' => $this->show->id,
            'from_phase' => $this->fromPhase,
            'to_phase' => $this->toPhase,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
```

### 5.5 Game Events

**GameStarted Event**:

```php
<?php

namespace App\Events\Games;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("show.{$this->game->show_id}"),
            new Channel("game.{$this->game->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'game.started';
    }

    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->game->id,
            'type' => $this->game->type,
            'round_number' => $this->game->round_number,
            'is_bonus' => $this->game->is_bonus,
            'players_at_start' => $this->game->players_at_start,
            'config' => $this->game->config,
            'started_at' => $this->game->started_at->toIso8601String(),
        ];
    }
}
```

**PlayerEliminated Event**:

```php
<?php

namespace App\Events\Games;

use App\Models\{Player, Game};
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerEliminated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Player $player,
        public Game $game,
        public string $reason
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("show.{$this->player->show_id}"),
            new Channel("player.{$this->player->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'player.eliminated';
    }

    public function broadcastWith(): array
    {
        return [
            'player_id' => $this->player->id,
            'player_number' => $this->player->player_number,
            'game_id' => $this->game->id,
            'game_type' => $this->game->type,
            'reason' => $this->reason,
            'elimination_order' => $this->player->elimination_order,
            'eliminated_at' => now()->toIso8601String(),
        ];
    }
}
```

### 5.6 Achievement Events

```bash
mkdir -p app/Events/Achievements
```

**AchievementUnlocked Event**:

```php
<?php

namespace App\Events\Achievements;

use App\Models\{PlayerAchievement, Player, Achievement};
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AchievementUnlocked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PlayerAchievement $playerAchievement
    ) {}

    public function broadcastOn(): array
    {
        $player = $this->playerAchievement->player;
        
        return [
            new Channel("show.{$player->show_id}"),
            new Channel("player.{$player->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'achievement.unlocked';
    }

    public function broadcastWith(): array
    {
        $achievement = $this->playerAchievement->achievement;
        $player = $this->playerAchievement->player;
        
        return [
            'player_id' => $player->id,
            'player_number' => $player->player_number,
            'achievement' => [
                'id' => $achievement->id,
                'key' => $achievement->key,
                'name_es' => $achievement->name_es,
                'name_en' => $achievement->name_en,
                'description_es' => $achievement->description_es,
                'description_en' => $achievement->description_en,
                'icon' => $achievement->icon,
                'rarity' => $achievement->rarity,
                'points' => $achievement->points,
            ],
            'context' => $this->playerAchievement->context,
            'unlocked_at' => $this->playerAchievement->unlocked_at->toIso8601String(),
        ];
    }
}
```

### 5.7 Audio Events

```bash
mkdir -p app/Events/Audio
```

**TrackStarted Event**:

```php
<?php

namespace App\Events\Audio;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrackStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $showId,
        public string $trackKey,
        public string $channel,
        public string $signedUrl,
        public ?int $durationMs = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("show.{$this->showId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'audio.track_started';
    }

    public function broadcastWith(): array
    {
        return [
            'track_key' => $this->trackKey,
            'channel' => $this->channel,
            'signed_url' => $this->signedUrl,
            'duration_ms' => $this->durationMs,
            'started_at' => now()->toIso8601String(),
        ];
    }
}
```

### 5.8 Scoreboard Events

```bash
mkdir -p app/Events/Scoreboard
```

**ScoreAdded Event**:

```php
<?php

namespace App\Events\Scoreboard;

use App\Models\PlayerScore;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScoreAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PlayerScore $score
    ) {}

    public function broadcastOn(): array
    {
        $player = $this->score->player;
        
        return [
            new Channel("show.{$player->show_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'scoreboard.score_added';
    }

    public function broadcastWith(): array
    {
        $player = $this->score->player;
        
        return [
            'player_id' => $player->id,
            'player_number' => $player->player_number,
            'game_id' => $this->score->game_id,
            'game_type' => $this->score->game_type,
            'raw_score' => $this->score->raw_score,
            'normalized_score' => $this->score->normalized_score,
            'metadata' => $this->score->metadata,
        ];
    }
}
```

### 5.9 Instructions Events

```bash
mkdir -p app/Events/Instructions
```

**InstructionsRequired Event**:

```php
<?php

namespace App\Events\Instructions;

use App\Models\{Game, GameInstruction};
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InstructionsRequired implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public GameInstruction $instruction
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("show.{$this->game->show_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'instructions.required';
    }

    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->game->id,
            'game_type' => $this->game->type,
            'instruction' => [
                'id' => $this->instruction->id,
                'content_es' => $this->instruction->content_es,
                'content_en' => $this->instruction->content_en,
                'audio_es_url' => $this->instruction->audio_es_url,
                'audio_en_url' => $this->instruction->audio_en_url,
                'estimated_duration_seconds' => $this->instruction->estimated_duration_seconds,
            ],
        ];
    }
}
```

### 5.10 Audit Events

```bash
mkdir -p app/Events/Audit
```

**AuditLogCreated Event**:

```php
<?php

namespace App\Events\Audit;

use App\Models\AuditLog;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AuditLogCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AuditLog $log
    ) {}

    public function broadcastOn(): array
    {
        // Only broadcast to supervisor channel
        if ($this->log->show_id) {
            return [
                new PrivateChannel("supervisor.{$this->log->show_id}"),
            ];
        }
        
        return [];
    }

    public function broadcastAs(): string
    {
        return 'audit.log_created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->log->id,
            'event_type' => $this->log->event_type,
            'entity_type' => $this->log->entity_type,
            'entity_id' => $this->log->entity_id,
            'user_id' => $this->log->user_id,
            'created_at' => $this->log->created_at->toIso8601String(),
            'payload_summary' => $this->getSummary(),
        ];
    }
    
    protected function getSummary(): string
    {
        // Return a brief summary instead of full payload
        return match($this->log->event_type) {
            'player_eliminated' => "Player #{$this->log->payload['player_number']} eliminated",
            'answer_submitted' => "Answer submitted",
            'vote_cast' => "Vote cast",
            'achievement_unlocked' => "Achievement: {$this->log->payload['achievement_key']}",
            default => $this->log->event_type,
        };
    }
}
```

### 5.11 Event Service Provider

Registrar listeners en `app/Providers/EventServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // Show events
        \App\Events\Show\ShowStarted::class => [],
        \App\Events\Show\PhaseChanged::class => [],
        
        // Game events
        \App\Events\Games\GameStarted::class => [],
        \App\Events\Games\PlayerEliminated::class => [
            \App\Listeners\LogPlayerElimination::class,
        ],
        
        // Achievement events
        \App\Events\Achievements\AchievementUnlocked::class => [
            \App\Listeners\UpdatePlayerScore::class,
        ],
        
        // Audio events
        \App\Events\Audio\TrackStarted::class => [
            \App\Listeners\RecordAudioPlay::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
```

### 5.12 Broadcasting Helper Service

```bash
mkdir -p app/Services/Broadcasting
touch app/Services/Broadcasting/BroadcastService.php
```

```php
<?php

namespace App\Services\Broadcasting;

use App\Models\{Show, Game, Player};
use Illuminate\Support\Facades\Log;

class BroadcastService
{
    /**
     * Broadcast show event
     */
    public function broadcastToShow(int $showId, string $event, array $data): void
    {
        broadcast(new \Illuminate\Broadcasting\BroadcastEvent($event, $data))
            ->toOthers();
        
        Log::debug("Broadcast to show", ['show_id' => $showId, 'event' => $event]);
    }
    
    /**
     * Broadcast game event
     */
    public function broadcastToGame(int $gameId, string $event, array $data): void
    {
        broadcast(new \Illuminate\Broadcasting\BroadcastEvent($event, $data))
            ->toOthers();
        
        Log::debug("Broadcast to game", ['game_id' => $gameId, 'event' => $event]);
    }
    
    /**
     * Broadcast to specific player
     */
    public function broadcastToPlayer(int $playerId, string $event, array $data): void
    {
        broadcast(new \Illuminate\Broadcasting\BroadcastEvent($event, $data))
            ->toOthers();
        
        Log::debug("Broadcast to player", ['player_id' => $playerId, 'event' => $event]);
    }
}
```

## Verificación

```bash
# Start Reverb
php artisan reverb:start

# Test broadcasting
php artisan tinker
>>> $show = \App\Models\Show::first();
>>> broadcast(new \App\Events\Show\ShowStarted($show));

# Check logs
tail -f storage/logs/reverb.log
```

## Sincronización con Frontend

El frontend debe:
1. Conectarse via Laravel Echo a `wss://0.0.0.0:8080`
2. Subscribirse a canales: `show.{id}`, `player.{id}`, `game.{id}`
3. Escuchar eventos: `.started`, `.phase_changed`, `.eliminated`, etc

Ver `websockets.md` en frontend para código Echo.

## Próximos Pasos

→ **06 - Authentication & Authorization**: Sanctum, guards, policies
