# Achievement System - Backend

## Descripción General

Sistema de logros (achievements) del backend que recompensa a los jugadores por acciones específicas durante el game show. El sistema es **event-driven** y utiliza WebSockets para notificaciones en tiempo real, integrándose con el sistema de alertas del frontend para mostrar toast notifications.

## Características Principales

- **35+ Logros Únicos**: Desde acciones básicas (unirse al juego) hasta hazañas épicas (ganar sin errores)
- **Progreso Incremental**: Logros con múltiples etapas (ej: "Responde 5/10/20 preguntas correctas")
- **Triggers Backend**: El servidor detecta automáticamente las condiciones y otorga logros
- **Broadcasting Real-Time**: Eventos WebSocket `AchievementUnlocked` y `AchievementProgress`
- **Sistema de Puntos**: Cada logro otorga puntos adicionales (10-500 pts según dificultad)
- **Integración con Scoreboard**: Puntos de logros suman al ranking global
- **Auditoría**: Todos los logros otorgados quedan registrados en audit logs

## Arquitectura

```
┌─────────────────────────────────────────────────────────┐
│                   Game Event Occurs                      │
│  (ej: Jugador responde pregunta correcta en Millonario) │
└──────────────────┬──────────────────────────────────────┘
                   │
                   v
┌─────────────────────────────────────────────────────────┐
│          AchievementService::checkTrigger()              │
│  - Detecta trigger type: OnCorrectAnswer                 │
│  - Valida condiciones del trigger_config JSON            │
│  - Consulta progreso actual del jugador                  │
└──────────────────┬──────────────────────────────────────┘
                   │
         ┌─────────┴──────────┐
         │ ¿Se cumple?        │
         └─────────┬──────────┘
                   │
        ┌──────────┴──────────┐
        │ NO                  │ SI
        v                     v
┌───────────────┐   ┌─────────────────────────────────────┐
│ updateProgress│   │   awardAchievement()                 │
│ +1 en DB      │   │   - Marca achievement como completed │
│               │   │   - Otorga puntos                    │
└───────┬───────┘   │   - Crea registro en player_scores  │
        │           │   - Log en audit_logs                │
        │           └─────────┬───────────────────────────┘
        │                     │
        └──────────┬──────────┘
                   v
┌─────────────────────────────────────────────────────────┐
│              Broadcast via Reverb                        │
│  Canal: private-player.{player_id}                       │
│  Evento: AchievementUnlocked o AchievementProgress       │
└──────────────────┬──────────────────────────────────────┘
                   │
                   v
┌─────────────────────────────────────────────────────────┐
│                  Frontend (Vue)                          │
│  - Alert toast con animación                             │
│  - Sonido de logro desbloqueado                          │
│  - Actualiza badge counter en UI                         │
└─────────────────────────────────────────────────────────┘
```

## Esquema de Base de Datos

### Tabla: `achievements`

Catálogo maestro de todos los logros disponibles en el juego.

```sql
CREATE TABLE achievements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) UNIQUE NOT NULL COMMENT 'Identificador único (ej: first_blood, perfect_millionaire)',
    
    -- Nombres y descripciones bilingües
    name_es VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NOT NULL,
    description_es TEXT NOT NULL,
    description_en TEXT NOT NULL,
    
    -- Visualización
    icon VARCHAR(50) NOT NULL COMMENT 'Emoji o nombre del ícono (ej: 🏆, trophy)',
    points INT NOT NULL DEFAULT 0 COMMENT 'Puntos otorgados al completar',
    
    -- Lógica de trigger
    trigger_type ENUM(
        'OnPlayerJoin',
        'OnCorrectAnswer',
        'OnIncorrectAnswer',
        'OnGameWon',
        'OnGameLost',
        'OnSurviveElimination',
        'OnPerfectSpelling',
        'OnFastWordFind',
        'OnLongFlappySurvival',
        'OnRopeVictory',
        'OnRouletteWin',
        'OnChatMessage',
        'OnReconnect',
        'OnMultipleGamesWon',
        'OnNoErrorsRun',
        'OnPlayAllGames'
    ) NOT NULL,
    
    trigger_config JSON COMMENT 'Configuración específica del trigger (ej: {"min_correct": 5, "game_type": "millionaire"})',
    
    -- Tipo de logro
    is_progressive BOOLEAN DEFAULT FALSE COMMENT 'Si tiene múltiples niveles (ej: 5, 10, 20 victorias)',
    max_progress INT DEFAULT 1 COMMENT 'Progreso máximo para completar (1 si no es progresivo)',
    
    -- Rareza
    rarity ENUM('common', 'rare', 'epic', 'legendary') DEFAULT 'common',
    
    -- Orden de visualización
    display_order INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_trigger_type (trigger_type),
    INDEX idx_rarity (rarity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tabla: `player_achievements`

Progreso y completitud de logros por jugador.

```sql
CREATE TABLE player_achievements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    achievement_id BIGINT UNSIGNED NOT NULL,
    
    -- Progreso
    progress INT NOT NULL DEFAULT 0 COMMENT 'Progreso actual (ej: 3 de 5 victorias)',
    max_progress INT NOT NULL COMMENT 'Progreso necesario para completar (denormalizado de achievements)',
    
    -- Estado
    completed_at TIMESTAMP NULL COMMENT 'Cuándo se completó (NULL = en progreso)',
    awarded_points INT NOT NULL DEFAULT 0 COMMENT 'Puntos otorgados (denormalizado)',
    
    -- Metadatos
    first_progress_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Cuándo inició el progreso',
    
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_player_achievement (player_id, achievement_id),
    INDEX idx_player_completed (player_id, completed_at),
    INDEX idx_achievement_completed (achievement_id, completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Service: `AchievementService.php`

### Ubicación

```
app/Services/Achievement/AchievementService.php
```

### Responsabilidades

1. **Detectar triggers**: Escuchar eventos del sistema y verificar condiciones
2. **Actualizar progreso**: Incrementar contadores de logros progresivos
3. **Otorgar logros**: Marcar como completado y asignar puntos
4. **Broadcasting**: Notificar al frontend via WebSockets
5. **Auditoría**: Registrar en audit_logs

### Métodos Principales

#### `checkTrigger(Player $player, string $triggerType, array $context = [])`

Verifica si un trigger debe otorgar o actualizar un logro.

```php
/**
 * Verifica triggers y otorga/actualiza logros según corresponda
 *
 * @param Player $player Jugador que ejecutó la acción
 * @param string $triggerType Tipo de trigger (ej: 'OnCorrectAnswer')
 * @param array $context Datos adicionales para validación (ej: ['game_type' => 'millionaire', 'streak' => 5])
 * @return Collection Logros otorgados o actualizados
 */
public function checkTrigger(Player $player, string $triggerType, array $context = []): Collection
{
    // 1. Obtener todos los achievements con este trigger_type
    $achievements = Achievement::where('trigger_type', $triggerType)->get();
    
    $affected = collect();
    
    foreach ($achievements as $achievement) {
        // 2. Validar trigger_config contra context
        if (!$this->matchesTriggerConfig($achievement->trigger_config, $context)) {
            continue;
        }
        
        // 3. Obtener o crear player_achievement
        $playerAchievement = PlayerAchievement::firstOrCreate(
            ['player_id' => $player->id, 'achievement_id' => $achievement->id],
            ['max_progress' => $achievement->max_progress]
        );
        
        // 4. Si ya está completado, skip
        if ($playerAchievement->completed_at) {
            continue;
        }
        
        // 5. Incrementar progreso
        $playerAchievement->increment('progress');
        
        // 6. ¿Se completó?
        if ($playerAchievement->progress >= $playerAchievement->max_progress) {
            $this->awardAchievement($player, $achievement, $playerAchievement);
        } else {
            $this->broadcastProgress($player, $achievement, $playerAchievement);
        }
        
        $affected->push($playerAchievement);
    }
    
    return $affected;
}
```

#### `awardAchievement(Player $player, Achievement $achievement, PlayerAchievement $playerAchievement)`

Otorga un logro completado.

```php
/**
 * Otorga un logro completado al jugador
 */
protected function awardAchievement(Player $player, Achievement $achievement, PlayerAchievement $playerAchievement): void
{
    DB::transaction(function () use ($player, $achievement, $playerAchievement) {
        // 1. Marcar como completado
        $playerAchievement->update([
            'completed_at' => now(),
            'awarded_points' => $achievement->points
        ]);
        
        // 2. Agregar puntos al scoreboard
        $player->scores()->create([
            'game_type' => 'achievement',
            'game_id' => $achievement->id,
            'raw_score' => $achievement->points,
            'normalized_score' => $achievement->points, // Ya está en escala 0-1000
            'metadata' => ['achievement_key' => $achievement->key]
        ]);
        
        // 3. Log de auditoría
        AuditService::log('achievement_awarded', [
            'actor_id' => $player->id,
            'actor_type' => 'player',
            'target_id' => $achievement->id,
            'target_type' => 'achievement',
            'context' => [
                'achievement_key' => $achievement->key,
                'points' => $achievement->points,
                'rarity' => $achievement->rarity
            ]
        ]);
        
        // 4. Broadcast evento
        $this->broadcastUnlocked($player, $achievement);
    });
}
```

#### `broadcastUnlocked(Player $player, Achievement $achievement)`

Notifica al frontend que un logro fue desbloqueado.

```php
/**
 * Broadcast achievement unlocked via WebSocket
 */
protected function broadcastUnlocked(Player $player, Achievement $achievement): void
{
    broadcast(new AchievementUnlocked(
        playerId: $player->id,
        achievementId: $achievement->id,
        achievementKey: $achievement->key,
        name: app()->getLocale() === 'es' ? $achievement->name_es : $achievement->name_en,
        description: app()->getLocale() === 'es' ? $achievement->description_es : $achievement->description_en,
        icon: $achievement->icon,
        points: $achievement->points,
        rarity: $achievement->rarity
    ))->toOthers();
}
```

#### `broadcastProgress(Player $player, Achievement $achievement, PlayerAchievement $playerAchievement)`

Notifica progreso de un logro progresivo.

```php
/**
 * Broadcast achievement progress via WebSocket
 */
protected function broadcastProgress(Player $player, Achievement $achievement, PlayerAchievement $playerAchievement): void
{
    broadcast(new AchievementProgress(
        playerId: $player->id,
        achievementId: $achievement->id,
        achievementKey: $achievement->key,
        current: $playerAchievement->progress,
        max: $playerAchievement->max_progress
    ))->toOthers();
}
```

## Eventos WebSocket

### `AchievementUnlocked`

Notifica que un logro fue completado.

**Canal**: `private-player.{player_id}`

**Payload**:
```json
{
    "player_id": 123,
    "achievement_id": 5,
    "achievement_key": "perfect_millionaire",
    "name": "Millonario Perfecto",
    "description": "Respondiste todas las preguntas correctamente sin errores",
    "icon": "🏆",
    "points": 500,
    "rarity": "legendary"
}
```

**Integración Frontend**:
- Alert toast con animación celebratoria
- Sonido `achievement-unlocked.mp3`
- Actualiza badge counter en profile
- Opcionalmente broadcast a canal público para mostrar a todos

### `AchievementProgress`

Notifica progreso en un logro incremental.

**Canal**: `private-player.{player_id}`

**Payload**:
```json
{
    "player_id": 123,
    "achievement_id": 8,
    "achievement_key": "word_hunter",
    "current": 7,
    "max": 10
}
```

**Integración Frontend**:
- Progress bar en UI de logros
- Notification silenciosa (sin toast)
- Actualiza badge si es milestone (50%, 75%, etc)

## Catálogo de 35+ Logros

### Categoría: Bienvenida (Common)

| Key | Nombre | Trigger | Config | Puntos |
|-----|--------|---------|--------|--------|
| `first_steps` | Primeros Pasos | `OnPlayerJoin` | - | 10 |
| `reconnection_hero` | Héroe de la Reconexión | `OnReconnect` | `{"min_reconnects": 3}` | 20 |

### Categoría: Millonario (Common → Epic)

| Key | Nombre | Trigger | Config | Puntos | Progreso |
|-----|--------|---------|--------|--------|----------|
| `quiz_apprentice` | Aprendiz de Quiz | `OnCorrectAnswer` | `{"game_type": "millionaire", "min_correct": 5}` | 50 | 5 |
| `quiz_master` | Maestro del Quiz | `OnCorrectAnswer` | `{"game_type": "millionaire", "min_correct": 10}` | 100 | 10 |
| `quiz_legend` | Leyenda del Quiz | `OnCorrectAnswer` | `{"game_type": "millionaire", "min_correct": 20}` | 200 | 20 |
| `perfect_millionaire` | Millonario Perfecto | `OnNoErrorsRun` | `{"game_type": "millionaire"}` | 500 | 1 |

### Categoría: Deletréalo (Rare)

| Key | Nombre | Trigger | Config | Puntos |
|-----|--------|---------|--------|--------|
| `perfect_spelling` | Ortografía Perfecta | `OnPerfectSpelling` | `{"max_attempts": 1}` | 100 |
| `bomb_defuser` | Desactivador de Bombas | `OnPerfectSpelling` | `{"time_remaining": 5}` | 150 |
| `spelling_streak` | Racha de Deletreo | `OnPerfectSpelling` | `{"consecutive": 3}` | 200 |

### Categoría: La Cuerda (Rare)

| Key | Nombre | Trigger | Config | Puntos |
|-----|--------|---------|--------|--------|
| `rope_survivor` | Sobreviviente de Cuerda | `OnRopeVictory` | - | 75 |
| `click_champion` | Campeón de Clicks | `OnRopeVictory` | `{"min_clicks": 100}` | 125 |
| `team_player` | Jugador de Equipo | `OnRopeVictory` | `{"voted_correctly": true}` | 150 |

### Categoría: Ruleta (Epic → Legendary)

| Key | Nombre | Trigger | Config | Puntos |
|-----|--------|---------|--------|--------|
| `lucky_spin` | Giro Afortunado | `OnRouletteWin` | `{"min_points": 500}` | 200 |
| `roulette_master` | Maestro de la Ruleta | `OnGameWon` | `{"game_type": "roulette"}` | 300 |
| `champion` | ¡Campeón! | `OnGameWon` | `{"is_final_game": true}` | 1000 |

### Categoría: Bonus Games (Common → Rare)

| Key | Nombre | Trigger | Config | Puntos |
|-----|--------|---------|--------|--------|
| `word_finder` | Buscador de Palabras | `OnFastWordFind` | `{"words_found": 5}` | 50 |
| `word_hunter` | Cazador de Palabras | `OnFastWordFind` | `{"words_found": 12}` | 150 |
| `speed_reader` | Lector Veloz | `OnFastWordFind` | `{"completion_time": 60}` | 200 |
| `flappy_survivor` | Sobreviviente Flappy | `OnLongFlappySurvival` | `{"min_seconds": 30}` | 100 |
| `flappy_master` | Maestro Flappy | `OnLongFlappySurvival` | `{"min_seconds": 60}` | 200 |

### Categoría: Supervivencia (Rare → Epic)

| Key | Nombre | Trigger | Config | Puntos | Progreso |
|-----|--------|---------|--------|--------|----------|
| `survivor` | Sobreviviente | `OnSurviveElimination` | `{"times": 1}` | 50 | 1 |
| `serial_survivor` | Sobreviviente Serial | `OnSurviveElimination` | `{"times": 3}` | 150 | 3 |
| `unstoppable` | Imparable | `OnSurviveElimination` | `{"times": 5}` | 300 | 5 |

### Categoría: Épicas (Legendary)

| Key | Nombre | Trigger | Config | Puntos |
|-----|--------|---------|--------|--------|
| `perfectionist` | Perfeccionista | `OnNoErrorsRun` | `{"all_games": true}` | 800 |
| `completionist` | Completista | `OnPlayAllGames` | `{"including_bonus": true}` | 500 |
| `first_blood` | Primera Sangre | `OnCorrectAnswer` | `{"is_first_in_game": true}` | 100 |
| `comeback_kid` | Niño del Regreso | `OnGameWon` | `{"was_last_place": true}` | 400 |

### Categoría: Social (Common)

| Key | Nombre | Trigger | Config | Puntos |
|-----|--------|---------|--------|--------|
| `chatty` | Conversador | `OnChatMessage` | `{"min_messages": 10}` | 20 |
| `emoji_master` | Maestro de Emojis | `OnChatMessage` | `{"emoji_only": true, "count": 20}` | 30 |

## Integración con Otros Sistemas

### 1. Scoreboard Unificado

Los puntos de logros se agregan al scoreboard:

```php
// En AchievementService::awardAchievement()
$player->scores()->create([
    'game_type' => 'achievement',
    'game_id' => $achievement->id,
    'raw_score' => $achievement->points,
    'normalized_score' => $achievement->points, // Ya normalizado 0-1000
    'metadata' => ['achievement_key' => $achievement->key]
]);
```

### 2. Audit System

Cada logro otorgado se registra:

```php
AuditService::log('achievement_awarded', [
    'actor_id' => $player->id,
    'actor_type' => 'player',
    'target_id' => $achievement->id,
    'target_type' => 'achievement',
    'context' => [
        'achievement_key' => $achievement->key,
        'points' => $achievement->points,
        'rarity' => $achievement->rarity
    ]
]);
```

### 3. Alert System (Frontend)

El frontend escucha `AchievementUnlocked` y muestra toast:

```typescript
// Frontend: achievement.store.ts
Echo.private(`player.${playerId}`)
    .listen('AchievementUnlocked', (data) => {
        alertStore.show({
            type: 'achievement',
            title: data.name,
            message: data.description,
            icon: data.icon,
            duration: 5000,
            sound: 'achievement-unlocked.mp3',
            rarity: data.rarity
        });
    });
```

## Endpoints API

### `GET /api/players/{player}/achievements`

Lista todos los logros del jugador (completados y en progreso).

**Response**:
```json
{
    "completed": [
        {
            "achievement_id": 5,
            "key": "perfect_millionaire",
            "name": "Millonario Perfecto",
            "description": "Respondiste todas las preguntas correctamente",
            "icon": "🏆",
            "points": 500,
            "rarity": "legendary",
            "completed_at": "2025-12-21T15:30:00Z"
        }
    ],
    "in_progress": [
        {
            "achievement_id": 8,
            "key": "word_hunter",
            "name": "Cazador de Palabras",
            "progress": 7,
            "max_progress": 10,
            "icon": "🔍",
            "rarity": "rare"
        }
    ],
    "locked": [
        {
            "achievement_id": 12,
            "key": "champion",
            "name": "???",
            "description": "Logro secreto. Sigue jugando para descubrirlo.",
            "icon": "🔒",
            "rarity": "legendary"
        }
    ]
}
```

### `GET /api/achievements`

Lista catálogo completo de logros (solo supervisor).

**Query params**:
- `rarity`: Filter by rarity
- `trigger_type`: Filter by trigger type
- `show_secret`: Include secret achievements (default: false)

## Seeders

### `AchievementSeeder.php`

Puebla la tabla `achievements` con los 35+ logros.

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Achievement;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            // Bienvenida
            [
                'key' => 'first_steps',
                'name_es' => 'Primeros Pasos',
                'name_en' => 'First Steps',
                'description_es' => '¡Bienvenido al juego! Has dado tus primeros pasos.',
                'description_en' => 'Welcome to the game! You\'ve taken your first steps.',
                'icon' => '👋',
                'points' => 10,
                'trigger_type' => 'OnPlayerJoin',
                'trigger_config' => null,
                'is_progressive' => false,
                'max_progress' => 1,
                'rarity' => 'common',
                'display_order' => 1
            ],
            
            // Millonario
            [
                'key' => 'quiz_apprentice',
                'name_es' => 'Aprendiz de Quiz',
                'name_en' => 'Quiz Apprentice',
                'description_es' => 'Responde 5 preguntas correctamente en el Millonario.',
                'description_en' => 'Answer 5 questions correctly in Millionaire.',
                'icon' => '📚',
                'points' => 50,
                'trigger_type' => 'OnCorrectAnswer',
                'trigger_config' => json_encode(['game_type' => 'millionaire', 'min_correct' => 5]),
                'is_progressive' => true,
                'max_progress' => 5,
                'rarity' => 'common',
                'display_order' => 10
            ],
            
            // ... (resto de logros)
            
            // Champion (final)
            [
                'key' => 'champion',
                'name_es' => '¡Campeón!',
                'name_en' => 'Champion!',
                'description_es' => 'Ganaste la Ruleta Final y eres el campeón del show.',
                'description_en' => 'You won the Final Roulette and are the show champion.',
                'icon' => '👑',
                'points' => 1000,
                'trigger_type' => 'OnGameWon',
                'trigger_config' => json_encode(['game_type' => 'roulette', 'is_final_game' => true]),
                'is_progressive' => false,
                'max_progress' => 1,
                'rarity' => 'legendary',
                'display_order' => 1000
            ],
        ];
        
        foreach ($achievements as $achievement) {
            Achievement::create($achievement);
        }
    }
}
```

## Testing

### Unit Tests

```php
// tests/Unit/AchievementServiceTest.php

public function test_awards_achievement_on_trigger()
{
    $player = Player::factory()->create();
    $achievement = Achievement::factory()->create([
        'trigger_type' => 'OnCorrectAnswer',
        'max_progress' => 1
    ]);
    
    $service = app(AchievementService::class);
    $result = $service->checkTrigger($player, 'OnCorrectAnswer');
    
    $this->assertCount(1, $result);
    $this->assertNotNull($player->achievements()->first()->completed_at);
}

public function test_tracks_progressive_achievement_progress()
{
    $player = Player::factory()->create();
    $achievement = Achievement::factory()->create([
        'trigger_type' => 'OnCorrectAnswer',
        'max_progress' => 5
    ]);
    
    $service = app(AchievementService::class);
    
    // Trigger 3 veces
    for ($i = 0; $i < 3; $i++) {
        $service->checkTrigger($player, 'OnCorrectAnswer');
    }
    
    $playerAchievement = $player->achievements()->first();
    $this->assertEquals(3, $playerAchievement->progress);
    $this->assertNull($playerAchievement->completed_at);
}
```

### Integration Tests

```php
// tests/Feature/AchievementBroadcastTest.php

public function test_broadcasts_achievement_unlocked_event()
{
    Event::fake([AchievementUnlocked::class]);
    
    $player = Player::factory()->create();
    $achievement = Achievement::factory()->create([
        'trigger_type' => 'OnPlayerJoin',
        'max_progress' => 1
    ]);
    
    $service = app(AchievementService::class);
    $service->checkTrigger($player, 'OnPlayerJoin');
    
    Event::assertDispatched(AchievementUnlocked::class);
}
```

## Consideraciones de Performance

### 1. Índices de Base de Datos

```sql
-- Ya definidos en schema
INDEX idx_trigger_type ON achievements(trigger_type);
INDEX idx_player_completed ON player_achievements(player_id, completed_at);
```

### 2. Caching

Cachear catálogo de achievements por trigger_type:

```php
$achievements = Cache::remember("achievements:trigger:{$triggerType}", 3600, function() use ($triggerType) {
    return Achievement::where('trigger_type', $triggerType)->get();
});
```

### 3. Queue Jobs

Para triggers pesados (ej: analizar todo el historial del jugador):

```php
dispatch(new CheckAchievementTrigger($player, 'OnPlayAllGames', $context));
```

## Futuras Mejoras

1. **Logros Secretos**: Ocultar nombre/descripción hasta desbloquear
2. **Leaderboard de Logros**: Ranking por total de logros completados
3. **Títulos/Badges**: Otorgar títulos especiales al completar sets de logros
4. **Achievements Temporales**: Logros solo disponibles en eventos especiales
5. **Social Sharing**: Compartir logros épicos en redes sociales

---

**Última actualización**: Diciembre 2025
