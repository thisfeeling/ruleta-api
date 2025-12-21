# Instructions System - Backend

## Descripción General

Sistema de instrucciones pre-game que muestra reglas, mecánicas y objetivos antes de cada juego eliminatorio o bonus. El backend almacena las instrucciones en la base de datos con soporte bilingüe (es-CO/en-US), las sirve via API, y controla el flujo mediante eventos WebSocket y state machine.

## Características Principales

- **Metadata por Juego**: Cada tipo de juego tiene instrucciones específicas
- **Bilingüe**: Contenido en español colombiano (es-CO) e inglés (en-US)
- **Media Embeds**: URLs de imágenes/videos explicativos
- **State Machine Integration**: Pausa automática antes de cada juego
- **Supervisor Controls**: Skip, replay, o force complete
- **Tracking**: Registro de quién leyó las instrucciones
- **WebSocket Events**: `InstructionsRequired`, `InstructionsCompleted`

## Arquitectura

```
┌────────────────────────────────────────────────────────┐
│        State Machine: GameStarting                      │
│  (Antes de millionaire/rope/spell/roulette/bonus)      │
└──────────────────┬─────────────────────────────────────┘
                   │
                   v
┌────────────────────────────────────────────────────────┐
│     InstructionsService::getForGame($gameType)         │
│  - Query DB: game_instructions WHERE game_type = X     │
│  - Ordenar por display_order                            │
│  - Retornar metadata (title, content, duration, media) │
└──────────────────┬─────────────────────────────────────┘
                   │
                   v
┌────────────────────────────────────────────────────────┐
│         Broadcast: InstructionsRequired                │
│  Canal: game.show (todos los jugadores)                 │
│  Payload: game_type + instructions metadata             │
└──────────────────┬─────────────────────────────────────┘
                   │
                   v
┌────────────────────────────────────────────────────────┐
│          Frontend: GameInstructions.vue                 │
│  - Modal fullscreen con instrucciones                   │
│  - Countdown timer (duration_seconds)                   │
│  - Media embeds (videos/imágenes)                       │
│  - Botón "Entendido" (manual) o auto-close (timeout)   │
└──────────────────┬─────────────────────────────────────┘
                   │
        ┌──────────┴──────────┐
        │ Player clicks        │ Timeout
        │ "Entendido"          │ expires
        v                      v
┌─────────────────────────────────────────────────────────┐
│     POST /api/instructions/{gameType}/complete          │
│  - Registra player_id como "leído"                       │
│  - Si todos leen O supervisor fuerza → continue          │
└──────────────────┬──────────────────────────────────────┘
                   │
                   v
┌─────────────────────────────────────────────────────────┐
│         Broadcast: InstructionsCompleted                │
│  Canal: game.show                                        │
│  Payload: game_type + completed_by                       │
└──────────────────┬──────────────────────────────────────┘
                   │
                   v
┌─────────────────────────────────────────────────────────┐
│        State Machine: TransitionTo(GamePlaying)         │
│  - Inicia el juego efectivamente                         │
└─────────────────────────────────────────────────────────┘
```

## Esquema de Base de Datos

### Tabla: `game_instructions`

Instrucciones maestras por tipo de juego.

```sql
CREATE TABLE game_instructions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Tipo de juego
    game_type VARCHAR(50) NOT NULL COMMENT 'millionaire, rope, spell, roulette, word_search, flappy',
    
    -- Contenido bilingüe
    title_es VARCHAR(255) NOT NULL,
    title_en VARCHAR(255) NOT NULL,
    content_es TEXT NOT NULL COMMENT 'Markdown o HTML',
    content_en TEXT NOT NULL COMMENT 'Markdown o HTML',
    
    -- Configuración
    duration_seconds INT NOT NULL DEFAULT 30 COMMENT 'Tiempo de visualización automática',
    
    -- Media
    media_urls JSON NULL COMMENT 'Array de URLs: imágenes, videos explicativos',
    
    -- Visualización
    display_order INT DEFAULT 0 COMMENT 'Orden si hay múltiples instrucciones para un juego',
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_game_type (game_type),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tabla: `player_instruction_reads` (Opcional - Tracking)

Registra qué jugadores leyeron las instrucciones.

```sql
CREATE TABLE player_instruction_reads (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id BIGINT UNSIGNED NOT NULL,
    show_id BIGINT UNSIGNED NOT NULL,
    game_type VARCHAR(50) NOT NULL,
    
    -- Metadatos
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    time_spent_seconds INT NULL COMMENT 'Tiempo que el jugador tuvo el modal abierto',
    
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_player_show_game (player_id, show_id, game_type),
    INDEX idx_show_game (show_id, game_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Service: `InstructionsService.php`

### Ubicación

```
app/Services/Game/InstructionsService.php
```

### Responsabilidades

1. **Recuperar instrucciones**: Query por game_type
2. **Tracking de lecturas**: Registrar qué jugadores leyeron
3. **Validar completitud**: ¿Todos los jugadores leyeron?
4. **Broadcasting**: Eventos WebSocket
5. **Supervisor controls**: Skip, replay, force complete

### Métodos Principales

#### `getForGame(string $gameType, string $locale = 'es'): ?GameInstruction`

Obtiene las instrucciones para un tipo de juego.

```php
<?php

namespace App\Services\Game;

use App\Models\GameInstruction;
use App\Events\InstructionsRequired;

class InstructionsService
{
    /**
     * Obtiene instrucciones para un tipo de juego
     *
     * @param string $gameType Tipo de juego (millionaire, rope, spell, etc)
     * @param string $locale Locale (es, en)
     * @return GameInstruction|null
     */
    public function getForGame(string $gameType, string $locale = 'es'): ?GameInstruction
    {
        $instruction = GameInstruction::where('game_type', $gameType)
            ->orderBy('display_order')
            ->first();
        
        if (!$instruction) {
            \Log::warning("No instructions found for game type: {$gameType}");
            return null;
        }
        
        return $instruction;
    }
    
    /**
     * Dispara evento de instrucciones requeridas
     *
     * @param string $gameType
     * @param int $showId
     */
    public function require(string $gameType, int $showId): void
    {
        $instruction = $this->getForGame($gameType, app()->getLocale());
        
        if (!$instruction) {
            // Si no hay instrucciones, continuar directamente
            return;
        }
        
        // Broadcast a todos los jugadores
        broadcast(new InstructionsRequired(
            showId: $showId,
            gameType: $gameType,
            instructions: [
                'id' => $instruction->id,
                'title' => app()->getLocale() === 'es' ? $instruction->title_es : $instruction->title_en,
                'content' => app()->getLocale() === 'es' ? $instruction->content_es : $instruction->content_en,
                'duration_seconds' => $instruction->duration_seconds,
                'media_urls' => $instruction->media_urls ?? []
            ]
        ))->toOthers();
    }
}
```

#### `markAsRead(Player $player, string $gameType, int $timeSpentSeconds = null): void`

Registra que un jugador leyó las instrucciones.

```php
use App\Models\PlayerInstructionRead;

/**
 * Marca instrucciones como leídas por un jugador
 *
 * @param Player $player
 * @param string $gameType
 * @param int|null $timeSpentSeconds Tiempo que tuvo el modal abierto
 */
public function markAsRead(Player $player, string $gameType, int $timeSpentSeconds = null): void
{
    PlayerInstructionRead::updateOrCreate(
        [
            'player_id' => $player->id,
            'show_id' => $player->show_id,
            'game_type' => $gameType
        ],
        [
            'time_spent_seconds' => $timeSpentSeconds
        ]
    );
    
    // Log de auditoría
    AuditService::log('player_action', [
        'actor_id' => $player->id,
        'actor_type' => 'player',
        'context' => [
            'action' => 'read_instructions',
            'game_type' => $gameType,
            'time_spent_seconds' => $timeSpentSeconds
        ]
    ]);
}
```

#### `allPlayersRead(int $showId, string $gameType): bool`

Verifica si todos los jugadores leyeron las instrucciones.

```php
/**
 * Verifica si todos los jugadores activos leyeron las instrucciones
 *
 * @param int $showId
 * @param string $gameType
 * @return bool
 */
public function allPlayersRead(int $showId, string $gameType): bool
{
    $activePlayers = Player::where('show_id', $showId)
        ->where('status', 'alive')
        ->count();
    
    $readCount = PlayerInstructionRead::where('show_id', $showId)
        ->where('game_type', $gameType)
        ->count();
    
    return $readCount >= $activePlayers;
}
```

#### `complete(int $showId, string $gameType, string $completedBy = 'timeout'): void`

Marca instrucciones como completadas y continúa el juego.

```php
use App\Events\InstructionsCompleted;

/**
 * Completa las instrucciones y continúa al juego
 *
 * @param int $showId
 * @param string $gameType
 * @param string $completedBy 'timeout', 'all_players', 'supervisor_skip'
 */
public function complete(int $showId, string $gameType, string $completedBy = 'timeout'): void
{
    // Broadcast completitud
    broadcast(new InstructionsCompleted(
        showId: $showId,
        gameType: $gameType,
        completedBy: $completedBy
    ))->toOthers();
    
    // Trigger state machine transition
    $show = Show::find($showId);
    $show->stateMachine()->transitionTo('playing');
    
    // Log de auditoría
    AuditService::log('game_state_change', [
        'actor_type' => $completedBy === 'supervisor_skip' ? 'supervisor' : 'system',
        'target_id' => $showId,
        'target_type' => 'show',
        'context' => [
            'from_state' => 'instructions',
            'to_state' => 'playing',
            'game_type' => $gameType,
            'completed_by' => $completedBy
        ]
    ]);
}
```

## Modelo Eloquent

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameInstruction extends Model
{
    protected $fillable = [
        'game_type', 'title_es', 'title_en', 'content_es', 'content_en',
        'duration_seconds', 'media_urls', 'display_order'
    ];
    
    protected $casts = [
        'media_urls' => 'array'
    ];
    
    /**
     * Obtiene el título en el idioma actual
     */
    public function getTitleAttribute(): string
    {
        return app()->getLocale() === 'es' ? $this->title_es : $this->title_en;
    }
    
    /**
     * Obtiene el contenido en el idioma actual
     */
    public function getContentAttribute(): string
    {
        return app()->getLocale() === 'es' ? $this->content_es : $this->content_en;
    }
}

class PlayerInstructionRead extends Model
{
    protected $fillable = [
        'player_id', 'show_id', 'game_type', 'time_spent_seconds'
    ];
    
    protected $casts = [
        'read_at' => 'datetime'
    ];
    
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
    
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }
}
```

## Eventos WebSocket

### `InstructionsRequired`

Notifica que se deben mostrar las instrucciones.

**Canal**: `game.show` (público)

**Payload**:
```json
{
    "show_id": 1,
    "game_type": "millionaire",
    "instructions": {
        "id": 1,
        "title": "¿Quién Quiere Ser Millonario?",
        "content": "**Objetivo**: Responde preguntas de cultura general.\n\n**Mecánica**:\n- 10 preguntas con 4 opciones\n- 30 segundos por pregunta\n- Al final, los jugadores con menor puntaje son eliminados\n\n**Puntuación**: 1 punto por respuesta correcta",
        "duration_seconds": 30,
        "media_urls": [
            "https://s3.../instructions_millionaire.mp4"
        ]
    }
}
```

**Integración Frontend**:
- Modal `GameInstructions.vue` fullscreen
- Countdown timer de `duration_seconds`
- Markdown rendering de `content`
- Video/imagen embeds de `media_urls`
- Botón "Entendido" envía `POST /api/instructions/{gameType}/complete`

### `InstructionsCompleted`

Notifica que las instrucciones fueron completadas.

**Canal**: `game.show` (público)

**Payload**:
```json
{
    "show_id": 1,
    "game_type": "millionaire",
    "completed_by": "all_players|timeout|supervisor_skip",
    "completed_at": "2025-12-21T15:30:00Z"
}
```

**Integración Frontend**:
- Cerrar modal `GameInstructions.vue`
- Transition a `GamePlaying` scene
- Preparar UI del juego específico

## Endpoints API

### `GET /api/games/{gameType}/instructions`

Obtiene las instrucciones de un juego.

**Path params**:
- `gameType`: string (`millionaire`, `rope`, `spell`, `roulette`, `word_search`, `flappy`)

**Query params**:
- `locale`: string (`es`, `en`) - default: `es`

**Response**:
```json
{
    "id": 1,
    "game_type": "millionaire",
    "title": "¿Quién Quiere Ser Millonario?",
    "content": "**Objetivo**: Responde preguntas...",
    "duration_seconds": 30,
    "media_urls": [
        "https://s3.../instructions_millionaire.mp4"
    ]
}
```

### `POST /api/instructions/{gameType}/complete`

Marca las instrucciones como completadas (jugador o supervisor).

**Path params**:
- `gameType`: string

**Body** (opcional):
```json
{
    "player_id": 23,
    "time_spent_seconds": 25
}
```

**Response**:
```json
{
    "success": true,
    "message": "Instructions marked as read",
    "all_players_read": false,
    "read_count": 15,
    "total_players": 30
}
```

**Nota**: Si `all_players_read === true` o supervisor fuerza, se dispara `InstructionsCompleted`.

### `POST /api/supervisor/instructions/{gameType}/skip`

Supervisor omite instrucciones y continúa al juego.

**Auth**: Requiere rol `supervisor`

**Response**:
```json
{
    "success": true,
    "message": "Instructions skipped, game starting",
    "event": "InstructionsCompleted"
}
```

### `POST /api/supervisor/instructions/{gameType}/replay`

Supervisor reproduce instrucciones nuevamente.

**Auth**: Requiere rol `supervisor`

**Response**:
```json
{
    "success": true,
    "message": "Instructions replayed",
    "event": "InstructionsRequired"
}
```

## Integración con State Machine

En `GameStateMachine.php`, antes de cada juego:

```php
<?php

namespace App\Services\Game;

class GameStateMachine
{
    /**
     * Inicia un juego (con instrucciones pre-game)
     *
     * @param string $gameType
     */
    public function startGame(string $gameType): void
    {
        // 1. Transición a estado "instructions"
        $this->show->update(['phase' => 'instructions']);
        
        // 2. Mostrar instrucciones
        $instructionsService = app(InstructionsService::class);
        $instructionsService->require($gameType, $this->show->id);
        
        // 3. Esperar completitud (auto-timeout o manual)
        // El frontend o supervisor llamará a /api/instructions/{gameType}/complete
        
        // 4. Cuando se complete, transición a "playing"
        // (manejado por InstructionsService::complete())
    }
}
```

## Seeder: `GameInstructionSeeder.php`

Puebla instrucciones para todos los juegos.

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GameInstruction;

class GameInstructionSeeder extends Seeder
{
    public function run(): void
    {
        $instructions = [
            // Millonario
            [
                'game_type' => 'millionaire',
                'title_es' => '¿Quién Quiere Ser Millonario?',
                'title_en' => 'Who Wants to Be a Millionaire?',
                'content_es' => "**Objetivo**: Responde preguntas de cultura general correctamente.\n\n**Mecánica**:\n- 10 preguntas con 4 opciones (A, B, C, D)\n- 30 segundos por pregunta\n- Al final del juego, los jugadores con menor puntaje son eliminados\n\n**Puntuación**: 1 punto por respuesta correcta\n\n**Consejo**: Lee todas las opciones antes de responder.",
                'content_en' => "**Objective**: Answer general knowledge questions correctly.\n\n**Mechanics**:\n- 10 questions with 4 options (A, B, C, D)\n- 30 seconds per question\n- At the end, players with lowest score are eliminated\n\n**Scoring**: 1 point per correct answer\n\n**Tip**: Read all options before answering.",
                'duration_seconds' => 30,
                'media_urls' => null,
                'display_order' => 0
            ],
            
            // La Cuerda
            [
                'game_type' => 'rope',
                'title_es' => 'La Cuerda',
                'title_en' => 'The Rope',
                'content_es' => "**Objetivo**: Trabaja en equipo para ganar la batalla de clicks.\n\n**Mecánica**:\n1. **Agrupación**: Serás asignado aleatoriamente a un grupo (A, B, C...)\n2. **Votación**: Vota por el miembro que crees dio menos clicks\n3. **Batalla**: ¡Haz click lo más rápido posible durante 60 segundos!\n\n**Eliminación**: El grupo con menos clicks totales pierde. Dentro del grupo perdedor, el jugador más votado es eliminado.\n\n**Consejo**: Click constante es mejor que ráfagas cortas.",
                'content_en' => "**Objective**: Work as a team to win the click battle.\n\n**Mechanics**:\n1. **Grouping**: You'll be randomly assigned to a group (A, B, C...)\n2. **Voting**: Vote for the member you think clicked the least\n3. **Battle**: Click as fast as possible for 60 seconds!\n\n**Elimination**: The group with fewest total clicks loses. Within the losing group, the most voted player is eliminated.\n\n**Tip**: Consistent clicking is better than short bursts.",
                'duration_seconds' => 45,
                'media_urls' => json_encode([
                    'https://s3.../rope_visual_demo.mp4'
                ]),
                'display_order' => 0
            ],
            
            // Deletréalo
            [
                'game_type' => 'spell',
                'title_es' => 'Deletréalo',
                'title_en' => 'Spell It',
                'content_es' => "**Objetivo**: Deletrea correctamente la palabra asignada.\n\n**Mecánica**:\n- Te asignaremos una palabra\n- Tendrás 30 segundos para deletrearla en voz alta\n- Tu audio será grabado y validado por supervisores\n- Una bomba 3D se inflará mientras el tiempo corre\n\n**Eliminación**: Si deletreas incorrectamente, serás eliminado.\n\n**Consejo**: Habla claro y con calma. Puedes repetir letras si no estás seguro.",
                'content_en' => "**Objective**: Spell the assigned word correctly.\n\n**Mechanics**:\n- You'll be assigned a word\n- You have 30 seconds to spell it out loud\n- Your audio will be recorded and validated by supervisors\n- A 3D bomb will inflate as time runs out\n\n**Elimination**: If you spell incorrectly, you'll be eliminated.\n\n**Tip**: Speak clearly and calmly. You can repeat letters if unsure.",
                'duration_seconds' => 35,
                'media_urls' => null,
                'display_order' => 0
            ],
            
            // Ruleta
            [
                'game_type' => 'roulette',
                'title_es' => 'La Ruleta Final',
                'title_en' => 'The Final Roulette',
                'content_es' => "**Objetivo**: Acumula más puntos que tus oponentes.\n\n**Mecánica**:\n- Cada jugador gira la ruleta por turnos\n- La ruleta tiene 12 segmentos con puntos variables (100-1000)\n- Tus puntos se acumulan en cada giro\n- El jugador con más puntos acumulados al final **¡GANA EL SHOW!**\n\n**Nota**: Este es el juego final. ¡El ganador se lleva todo!\n\n**Consejo**: Confía en la suerte, todos tienen las mismas probabilidades.",
                'content_en' => "**Objective**: Accumulate more points than your opponents.\n\n**Mechanics**:\n- Each player spins the roulette in turns\n- The roulette has 12 segments with variable points (100-1000)\n- Your points accumulate with each spin\n- The player with most accumulated points at the end **WINS THE SHOW!**\n\n**Note**: This is the final game. Winner takes all!\n\n**Tip**: Trust your luck, everyone has equal odds.",
                'duration_seconds' => 40,
                'media_urls' => null,
                'display_order' => 0
            ],
            
            // Word Search (Bonus)
            [
                'game_type' => 'word_search',
                'title_es' => '¡A Buscar! (Bonus)',
                'title_en' => 'Word Hunt! (Bonus)',
                'content_es' => "**Tipo**: Juego BONUS (no eliminatorio)\n\n**Objetivo**: Encuentra palabras ocultas en la sopa de letras.\n\n**Mecánica**:\n- Grid de 15×15 letras\n- 12 palabras escondidas (horizontal, vertical, diagonal)\n- 5 minutos para encontrar todas\n- Haz click y arrastra para seleccionar palabras\n\n**Puntuación**: 1000 pts si encuentras todas, proporcional si encuentras menos\n\n**Nota**: Tus puntos suman al scoreboard global, pero **NO serás eliminado**.",
                'content_en' => "**Type**: BONUS game (non-eliminating)\n\n**Objective**: Find hidden words in the word search.\n\n**Mechanics**:\n- 15×15 letter grid\n- 12 hidden words (horizontal, vertical, diagonal)\n- 5 minutes to find all\n- Click and drag to select words\n\n**Scoring**: 1000 pts if you find all, proportional if less\n\n**Note**: Your points add to global scoreboard, but you **WON'T be eliminated**.",
                'duration_seconds' => 30,
                'media_urls' => null,
                'display_order' => 0
            ],
            
            // Flappy (Bonus)
            [
                'game_type' => 'flappy',
                'title_es' => 'No Lo Choques (Bonus)',
                'title_en' => 'Don\'t Crash It (Bonus)',
                'content_es' => "**Tipo**: Juego BONUS (no eliminatorio)\n\n**Objetivo**: Sobrevive el mayor tiempo posible sin chocar.\n\n**Mecánica**:\n- Haz click o presiona espacio para volar\n- Evita las tuberías\n- El juego termina cuando chocas o pasas 2 minutos\n\n**Puntuación**: Basada en tiempo sobrevivido (más tiempo = más puntos)\n\n**Nota**: Tus puntos suman al scoreboard global, pero **NO serás eliminado**.\n\n**Consejo**: Clicks suaves y constantes. No te desesperes.",
                'content_en' => "**Type**: BONUS game (non-eliminating)\n\n**Objective**: Survive as long as possible without crashing.\n\n**Mechanics**:\n- Click or press space to fly\n- Avoid the pipes\n- Game ends when you crash or after 2 minutes\n\n**Scoring**: Based on survival time (longer = more points)\n\n**Note**: Your points add to global scoreboard, but you **WON'T be eliminated**.\n\n**Tip**: Smooth, consistent clicks. Don't panic.",
                'duration_seconds' => 30,
                'media_urls' => null,
                'display_order' => 0
            ],
        ];
        
        foreach ($instructions as $instruction) {
            GameInstruction::create($instruction);
        }
    }
}
```

## Testing

### Unit Tests

```php
// tests/Unit/InstructionsServiceTest.php

public function test_gets_instructions_for_game()
{
    $instruction = GameInstruction::factory()->create(['game_type' => 'millionaire']);
    
    $service = app(InstructionsService::class);
    $result = $service->getForGame('millionaire');
    
    $this->assertNotNull($result);
    $this->assertEquals('millionaire', $result->game_type);
}

public function test_marks_player_as_read()
{
    $player = Player::factory()->create();
    $service = app(InstructionsService::class);
    
    $service->markAsRead($player, 'millionaire', 25);
    
    $this->assertDatabaseHas('player_instruction_reads', [
        'player_id' => $player->id,
        'game_type' => 'millionaire',
        'time_spent_seconds' => 25
    ]);
}
```

### Integration Tests

```php
// tests/Feature/InstructionsAPITest.php

public function test_can_get_instructions_for_game()
{
    GameInstruction::factory()->create(['game_type' => 'millionaire']);
    
    $response = $this->get('/api/games/millionaire/instructions');
    
    $response->assertOk()
        ->assertJsonStructure([
            'id', 'game_type', 'title', 'content', 'duration_seconds', 'media_urls'
        ]);
}

public function test_supervisor_can_skip_instructions()
{
    Event::fake([InstructionsCompleted::class]);
    
    $supervisor = User::factory()->supervisor()->create();
    
    $this->actingAs($supervisor)
        ->post('/api/supervisor/instructions/millionaire/skip')
        ->assertOk();
    
    Event::assertDispatched(InstructionsCompleted::class);
}
```

## Consideraciones de UX

### 1. Timeout Automático

Si ningún jugador cierra el modal después de `duration_seconds`, se auto-completa:

```javascript
// Frontend: GameInstructions.vue
let countdown = ref(instructions.duration_seconds);

const timer = setInterval(() => {
  countdown.value--;
  if (countdown.value <= 0) {
    clearInterval(timer);
    autoComplete();
  }
}, 1000);

function autoComplete() {
  api.post(`/api/instructions/${gameType}/complete`, {
    player_id: currentPlayer.id,
    time_spent_seconds: instructions.duration_seconds
  });
}
```

### 2. Supervisor Override

Supervisor puede:
- **Skip**: Saltar instrucciones inmediatamente
- **Replay**: Volver a mostrarlas si alguien se perdió
- **Force Complete**: Forzar completitud aunque no todos hayan leído

### 3. Mobile-Friendly

Instrucciones deben ser legibles en pantallas pequeñas:
- Font size responsive
- Videos con controles nativos
- Scroll si el contenido es largo

## Futuras Mejoras

1. **Instrucciones Interactivas**: Mini-tutorial interactivo antes del juego
2. **Versiones Simplificadas**: "Quick rules" vs "Full rules"
3. **Quiz de Comprensión**: Pregunta rápida para verificar que entendieron
4. **Historial**: Poder revisar instrucciones durante el juego (sidebar)
5. **A/B Testing**: Diferentes versiones de instrucciones para ver cuál funciona mejor

---

**Última actualización**: Diciembre 2025
