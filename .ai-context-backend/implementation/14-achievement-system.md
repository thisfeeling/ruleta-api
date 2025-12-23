# 14 - Achievement System

**Status**: [x] Completed

## Objetivo

Implementar sistema de logros con triggers automáticos y manual unlocking.

## Dependencias

- **Anterior**: 08-13 - All Games
- **Sincronización Frontend**: Eventos de logros se muestran en UI con toasts

## Implementación

### 14.1 Achievement Service

```php
<?php

namespace App\Services\Achievement;

use App\Models\{Player, Achievement, PlayerAchievement, AchievementProgress};
use App\Events\Achievements\AchievementUnlocked;

class AchievementService
{
    public function unlock(Player $player, string $achievementKey, ?array $context = null): ?PlayerAchievement
    {
        $achievement = Achievement::where('key', $achievementKey)->first();
        
        if (!$achievement) {
            return null;
        }
        
        // Check if already unlocked
        $existing = PlayerAchievement::where('player_id', $player->id)
            ->where('achievement_id', $achievement->id)
            ->where('show_id', $player->show_id)
            ->first();
        
        if ($existing) {
            return $existing;
        }
        
        // Create unlock record
        $playerAchievement = PlayerAchievement::create([
            'player_id' => $player->id,
            'achievement_id' => $achievement->id,
            'show_id' => $player->show_id,
            'unlocked_at' => now(),
            'context' => $context,
        ]);
        
        // Add points to player
        $player->increment('total_score', $achievement->points);
        
        // Broadcast event
        event(new AchievementUnlocked($playerAchievement));
        
        return $playerAchievement;
    }

    public function updateProgress(Player $player, string $achievementKey, int $progress, int $required): void
    {
        $achievement = Achievement::where('key', $achievementKey)->first();
        
        if (!$achievement) {
            return;
        }
        
        AchievementProgress::updateOrCreate(
            [
                'player_id' => $player->id,
                'achievement_id' => $achievement->id,
            ],
            [
                'current_progress' => $progress,
                'required_progress' => $required,
            ]
        );
        
        // Check if completed
        if ($progress >= $required) {
            $this->unlock($player, $achievementKey, ['progress' => $progress]);
        }
    }

    public function getPlayerAchievements(Player $player): \Illuminate\Support\Collection
    {
        return PlayerAchievement::where('player_id', $player->id)
            ->with('achievement')
            ->get();
    }

    public function getProgress(Player $player): \Illuminate\Support\Collection
    {
        return AchievementProgress::where('player_id', $player->id)
            ->with('achievement')
            ->get();
    }
}
```

### 14.2 Achievement Triggers

```php
<?php

namespace App\Services\Achievement\Triggers;

use App\Models\{Player, MillionaireAnswer};
use App\Services\Achievement\AchievementService;

class MillionaireTrigger
{
    protected AchievementService $achievements;

    public function __construct(AchievementService $achievements)
    {
        $this->achievements = $achievements;
    }

    public function checkPerfectGame(Player $player, int $gameId): void
    {
        $answers = MillionaireAnswer::whereHas('question', fn($q) => $q->where('game_id', $gameId))
            ->where('player_id', $player->id)
            ->get();
        
        if ($answers->every(fn($a) => $a->is_correct)) {
            $this->achievements->unlock($player, 'millionaire_perfect', [
                'game_id' => $gameId,
                'total_questions' => $answers->count(),
            ]);
        }
    }

    public function checkSpeedDemon(Player $player, MillionaireAnswer $answer): void
    {
        if ($answer->time_taken_ms < 3000) {
            // Check last 5 answers
            $recentFast = MillionaireAnswer::where('player_id', $player->id)
                ->where('time_taken_ms', '<', 3000)
                ->orderByDesc('created_at')
                ->limit(5)
                ->count();
            
            if ($recentFast >= 5) {
                $this->achievements->unlock($player, 'millionaire_speed_demon');
            }
        }
    }
}
```

### 14.3 Achievement Listeners

```php
<?php

namespace App\Listeners;

use App\Events\Games\PlayerEliminated;
use App\Services\Achievement\AchievementService;

class CheckFirstBloodAchievement
{
    protected AchievementService $achievements;

    public function __construct(AchievementService $achievements)
    {
        $this->achievements = $achievements;
    }

    public function handle(PlayerEliminated $event): void
    {
        if ($event->player->elimination_order === 1) {
            $this->achievements->unlock($event->player, 'first_blood', [
                'game' => $event->game->type,
            ]);
        }
    }
}
```

Register in `EventServiceProvider`:

```php
protected $listen = [
    \App\Events\Games\PlayerEliminated::class => [
        \App\Listeners\CheckFirstBloodAchievement::class,
    ],
];
```

### 14.4 Achievement Controller

```php
<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\Achievement\AchievementService;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    protected AchievementService $achievements;

    public function __construct(AchievementService $achievements)
    {
        $this->achievements = $achievements;
    }

    public function index(Request $request)
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        
        if (!$player) {
            return response()->json(['error' => 'Player not found'], 404);
        }
        
        return response()->json([
            'achievements' => $this->achievements->getPlayerAchievements($player),
            'progress' => $this->achievements->getProgress($player),
        ]);
    }

    public function all()
    {
        return response()->json(\App\Models\Achievement::all());
    }
}
```

## Próximos Pasos

→ **15 - Audit System**: Dual storage (DB + S3)
