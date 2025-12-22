# 03 - Models & Relationships

**Status**: [ ] Not Started

## Objetivo

Crear todos los modelos Eloquent con sus relaciones, accessors, scopes y casts.

## Dependencias

- **Anterior**: 02 - Database Schema
- **Sincronización Frontend**: Las interfaces TypeScript deben mapear a estos modelos

## Modelos a Crear

### Core Models
1. `User` - Usuario base (player/supervisor)
2. `Show` - Show/episodio
3. `Player` - Jugador en un show
4. `PlayerScore` - Puntuación unificada

### Game Models
5. `Game` - Juego individual
6. `MillionaireQuestion` - Pregunta del Millonario
7. `MillionaireAnswer` - Respuesta de jugador
8. `RopeGroup` - Grupo en La Cuerda
9. `RopeVote` - Voto de eliminación
10. `SpellWord` - Palabra a deletrear
11. `SpellAttempt` - Intento de deletreo
12. `RouletteSpin` - Giro de ruleta
13. `WordSearchGrid` - Grilla de sopa de letras
14. `WordSearchFind` - Palabra encontrada
15. `FlappyAttempt` - Intento de Flappy

### System Models
16. `Achievement` - Definición de logro
17. `PlayerAchievement` - Logro desbloqueado
18. `AchievementProgress` - Progreso de logro
19. `AudioTrack` - Track de audio
20. `AudioPlay` - Reproducción de audio
21. `AuditLog` - Log de auditoría
22. `GameInstruction` - Instrucciones de juego
23. `InstructionRead` - Lectura de instrucción

## Implementación

### 3.1 User Model

```bash
php artisan make:model User
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Relationships
    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function supervisedShows(): HasMany
    {
        return $this->hasMany(Show::class, 'supervisor_id');
    }

    public function audioPlays(): HasMany
    {
        return $this->hasMany(AudioPlay::class, 'played_by');
    }

    // Scopes
    public function scopeRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopePlayers($query)
    {
        return $query->where('role', 'player');
    }

    public function scopeSupervisors($query)
    {
        return $query->where('role', 'supervisor');
    }

    // Helpers
    public function isSupervisor(): bool
    {
        return $this->role === 'supervisor';
    }

    public function isPlayer(): bool
    {
        return $this->role === 'player';
    }
}
```

### 3.2 Show Model

```bash
php artisan make:model Show
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{HasMany, BelongsTo};

class Show extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'status',
        'current_phase',
        'max_players',
        'current_player_count',
        'winner_id',
        'scheduled_at',
        'started_at',
        'completed_at',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function activePlayers(): HasMany
    {
        return $this->hasMany(Player::class)->where('status', 'active');
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_at', '>', now());
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->status === 'in_progress';
    }

    public function canAcceptPlayers(): bool
    {
        return $this->status === 'lobby' 
            && $this->current_player_count < $this->max_players;
    }

    public function getActivePlayerCount(): int
    {
        return $this->players()->where('status', 'active')->count();
    }
}
```

### 3.3 Player Model

```bash
php artisan make:model Player
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, BelongsToMany};

class Player extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'show_id',
        'user_id',
        'player_number',
        'pin',
        'status',
        'elimination_order',
        'eliminated_by_game',
        'total_score',
        'stats',
    ];

    protected $casts = [
        'stats' => 'array',
    ];

    // Relationships
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(PlayerScore::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(PlayerAchievement::class);
    }

    public function achievementProgress(): HasMany
    {
        return $this->hasMany(AchievementProgress::class);
    }

    public function millionaireAnswers(): HasMany
    {
        return $this->hasMany(MillionaireAnswer::class);
    }

    public function spellWords(): HasMany
    {
        return $this->hasMany(SpellWord::class);
    }

    public function rouletteSpins(): HasMany
    {
        return $this->hasMany(RouletteSpin::class);
    }

    public function wordSearchFinds(): HasMany
    {
        return $this->hasMany(WordSearchFind::class);
    }

    public function flappyAttempts(): HasMany
    {
        return $this->hasMany(FlappyAttempt::class);
    }

    public function ropeGroups(): BelongsToMany
    {
        return $this->belongsToMany(RopeGroup::class, 'group_player')
            ->withPivot('clicks_contributed')
            ->withTimestamps();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeEliminated($query)
    {
        return $query->where('status', 'eliminated');
    }

    public function scopeInShow($query, int $showId)
    {
        return $query->where('show_id', $showId);
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isEliminated(): bool
    {
        return $this->status === 'eliminated';
    }

    public function eliminate(string $game, int $order): void
    {
        $this->update([
            'status' => 'eliminated',
            'eliminated_by_game' => $game,
            'elimination_order' => $order,
        ]);
    }

    public function addScore(int $gameId, string $gameType, int $rawScore, int $normalizedScore, ?array $metadata = null): PlayerScore
    {
        return $this->scores()->create([
            'game_id' => $gameId,
            'game_type' => $gameType,
            'raw_score' => $rawScore,
            'normalized_score' => $normalizedScore,
            'metadata' => $metadata,
        ]);
    }
}
```

### 3.4 PlayerScore Model

```bash
php artisan make:model PlayerScore
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'game_id',
        'game_type',
        'raw_score',
        'normalized_score',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Relationships
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    // Scopes
    public function scopeForGame($query, string $gameType)
    {
        return $query->where('game_type', $gameType);
    }

    public function scopeTopScores($query, int $limit = 10)
    {
        return $query->orderByDesc('normalized_score')->limit($limit);
    }

    // Static normalizers (0-1000 scale)
    public static function normalizeMillionaire(int $correct, int $total): int
    {
        return (int) (($correct / $total) * 1000);
    }

    public static function normalizeRope(int $clicks): int
    {
        return min(1000, (int) (($clicks / 200) * 1000));
    }

    public static function normalizeSpell(bool $correct, int $timeTakenMs, int $timeLimit): int
    {
        if (!$correct) return 0;
        $timeRatio = $timeTakenMs / ($timeLimit * 1000);
        return (int) ((1 - $timeRatio) * 1000);
    }

    public static function normalizeRoulette(int $totalPoints): int
    {
        return min(1000, $totalPoints);
    }

    public static function normalizeWordSearch(int $wordsFound, int $timeMs): int
    {
        $baseScore = $wordsFound * 100;
        $timeBonus = max(0, (180000 - $timeMs) / 180);
        return min(1000, (int) ($baseScore + $timeBonus));
    }

    public static function normalizeFlappy(int $survivalTimeMs): int
    {
        return min(1000, (int) ($survivalTimeMs / 100));
    }
}
```

### 3.5 Game Model

```bash
php artisan make:model Game
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'show_id',
        'type',
        'round_number',
        'status',
        'is_bonus',
        'players_at_start',
        'players_eliminated',
        'config',
        'state',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'is_bonus' => 'boolean',
        'config' => 'array',
        'state' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(PlayerScore::class);
    }

    public function millionaireQuestions(): HasMany
    {
        return $this->hasMany(MillionaireQuestion::class);
    }

    public function ropeGroups(): HasMany
    {
        return $this->hasMany(RopeGroup::class);
    }

    public function spellWords(): HasMany
    {
        return $this->hasMany(SpellWord::class);
    }

    public function rouletteSpins(): HasMany
    {
        return $this->hasMany(RouletteSpin::class);
    }

    public function wordSearchGrid(): HasMany
    {
        return $this->hasMany(WordSearchGrid::class);
    }

    public function flappyAttempts(): HasMany
    {
        return $this->hasMany(FlappyAttempt::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBonus($query)
    {
        return $query->where('is_bonus', true);
    }

    public function scopeMain($query)
    {
        return $query->where('is_bonus', false);
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function start(): void
    {
        $this->update([
            'status' => 'active',
            'started_at' => now(),
            'players_at_start' => $this->show->getActivePlayerCount(),
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
```

### 3.6 Achievement Model

```bash
php artisan make:model Achievement
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name_es',
        'name_en',
        'description_es',
        'description_en',
        'icon',
        'rarity',
        'points',
        'is_secret',
        'unlock_criteria',
    ];

    protected $casts = [
        'is_secret' => 'boolean',
        'unlock_criteria' => 'array',
    ];

    // Relationships
    public function playerAchievements(): HasMany
    {
        return $this->hasMany(PlayerAchievement::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(AchievementProgress::class);
    }

    // Scopes
    public function scopeRarity($query, string $rarity)
    {
        return $query->where('rarity', $rarity);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_secret', false);
    }

    // Helpers
    public function getName(string $locale = 'es'): string
    {
        return $locale === 'en' ? $this->name_en : $this->name_es;
    }

    public function getDescription(string $locale = 'es'): string
    {
        return $locale === 'en' ? $this->description_en : $this->description_es;
    }
}
```

### 3.7 AudioTrack Model

```bash
php artisan make:model AudioTrack
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AudioTrack extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'channel',
        's3_path',
        's3_url',
        'duration_ms',
        'default_volume',
        'metadata',
        'is_preloaded',
    ];

    protected $casts = [
        'default_volume' => 'float',
        'metadata' => 'array',
        'is_preloaded' => 'boolean',
    ];

    // Relationships
    public function plays(): HasMany
    {
        return $this->hasMany(AudioPlay::class, 'track_id');
    }

    // Scopes
    public function scopeChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopePreloaded($query)
    {
        return $query->where('is_preloaded', true);
    }

    public function scopeKey($query, string $key)
    {
        return $query->where('key', $key);
    }

    // Helpers
    public function getSignedUrl(int $expiresInMinutes = 60): string
    {
        // Generate signed URL for S3
        return \Storage::disk('s3')->temporaryUrl(
            $this->s3_path,
            now()->addMinutes($expiresInMinutes)
        );
    }

    public function recordPlay(?int $showId = null, ?int $userId = null, ?string $context = null): void
    {
        $this->plays()->create([
            'show_id' => $showId,
            'played_by' => $userId,
            'played_at' => now(),
            'context' => $context,
        ]);
    }
}
```

### 3.8 AuditLog Model

```bash
php artisan make:model AuditLog
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;
    
    protected $fillable = [
        'show_id',
        'user_id',
        'event_type',
        'entity_type',
        'entity_id',
        'payload',
        'ip_address',
        'user_agent',
        's3_backup_key',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeEventType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeForShow($query, int $showId)
    {
        return $query->where('show_id', $showId);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>', now()->subDays($days));
    }

    // Helpers
    public static function log(
        string $eventType,
        array $payload,
        ?int $showId = null,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): self {
        return self::create([
            'show_id' => $showId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $payload,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
```

## Otros Modelos (Simplified)

Para los modelos restantes del juego (MillionaireQuestion, RopeGroup, SpellWord, etc), seguir este patrón:

1. Definir `$fillable` con todos los campos
2. Agregar `$casts` para JSON, fechas, booleans
3. Crear relationships con `belongsTo`, `hasMany`, etc
4. Agregar scopes útiles
5. Métodos helper específicos del juego

## Trait Reutilizable: Translatable

```php
<?php

namespace App\Models\Traits;

trait Translatable
{
    public function translate(string $field, string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $suffix = $locale === 'en' ? '_en' : '_es';
        $fieldName = $field . $suffix;
        
        return $this->$fieldName ?? null;
    }
}
```

Usar en modelos con campos `*_es` y `*_en`:

```php
class Achievement extends Model
{
    use Translatable;
    
    public function getName(?string $locale = null): string
    {
        return $this->translate('name', $locale);
    }
}
```

## Verificación

```bash
php artisan tinker

# Test relationships
>>> $show = Show::first();
>>> $show->players;
>>> $show->games;

# Test scopes
>>> Player::active()->count();
>>> Game::type('millionaire')->first();

# Test helpers
>>> $player = Player::first();
>>> $player->isActive();
>>> $player->addScore(1, 'millionaire', 8, 800);
```

## Sincronización con Frontend

Las interfaces TypeScript deben coincidir con estos modelos:
- Mismos nombres de campos
- Mismas enums
- Mismas relaciones (como propiedades opcionales)

## Próximos Pasos

→ **04 - Core Services**: Audio, Storage, S3, TTS
