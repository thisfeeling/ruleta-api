# Reverb WebSockets - Ruleta Familiar

## Arquitectura de Reverb

Laravel Reverb es el servidor WebSocket nativo de Laravel que vive **dentro** del mismo contenedor de Laravel.

```
Laravel Container
├── PHP-FPM (HTTP)
└── Reverb (WebSocket) ← mismo proceso supervisado
```

## Inicio de Reverb

### Desarrollo
```bash
php artisan reverb:start
```

### Producción (Supervisor)
```conf
[program:reverb]
command=php artisan reverb:start --host=0.0.0.0 --port=8080
directory=/var/www/html
autostart=true
autorestart=true
user=www-data
```

## Canales

### Públicos
```php
// show.{id} - Estado general del show
Broadcast::channel('show.{id}', function ($user, $id) {
    return true; // Todos pueden ver
});

// chat - Chat global
Broadcast::channel('chat', function ($user) {
    return true;
});
```

### Privados
```php
// player.{id} - Notificaciones personales
Broadcast::channel('player.{id}', function ($user, $id) {
    return $user->id === (int) $id;
});

// game.{type} - Estado del juego actual
Broadcast::channel('game.{type}', function ($user, $type) {
    return $user->status === 'alive';
});
```

### Presence
```php
// supervisor.show - Supervisores online
Broadcast::channel('supervisor.show', function ($user) {
    return $user->role === 'supervisor' ? [
        'id' => $user->id,
        'nickname' => $user->nickname,
    ] : null;
});
```

## Eventos Principales

### Player Events
```php
PlayerJoined              // Jugador se une al show
PlayerReconnected         // Jugador reconecta con PIN
PlayerEliminated          // Jugador eliminado (incluye audio_url narración TTS)
PlayerPassed              // Jugador avanza de fase
```

### Show Events
```php
ShowStateChanged          // Cambio de fase (lobby → millionaire → rope → etc)
GameStarted               // Juego inicia (millionaire, rope, spell, roulette, word_search, flappy)
GameEnded                 // Juego termina
ScreenChanged             // Pantalla de transición
```

### Audio Events
```php
AudioRequested            // Audio solicitado por backend
NumberCalled              // Número de jugador llamado (narración)
TrackStarted              // Track de audio inicia (music/sfx/voice)
TrackEnded                // Track de audio termina
VolumeChanged             // Volumen de canal cambia (music/sfx/voice)
```

### Chat Events
```php
ChatMessageSent           // Mensaje de chat enviado
```

### Achievement Events
```php
AchievementUnlocked       // Logro desbloqueado (toast notification)
AchievementProgress       // Progreso de logro incremental
```

### Instruction Events
```php
InstructionsRequired      // Pre-game instructions needed
InstructionsCompleted     // Instrucciones completadas por jugador/supervisor
```

### Audit Events
```php
AuditLogCreated           // Log de auditoría crítico (solo para supervisors)
```

### Game-Specific Events
```php
// Millonario
AnswerValidated           // Respuesta validada (correcta/incorrecta)

// La Cuerda
RopeStateUpdated          // Estado de tensión actualizado (grouping/voting/battling)

// Deletréalo
SpellValidated            // Audio de deletreo validado por supervisor

// Ruleta
SpinResult                // Resultado del giro

// Word Search (Bonus)
WordFound                 // Palabra encontrada en sopa de letras
WordSearchCompleted       // Jugador completó todas las palabras

// Flappy (Bonus)
FlappyCrashed             // Jugador chocó en Flappy
FlappyScoreUpdated        // Score actualizado (pipes passed)
```

## Cliente (Vue + laravel-echo)

```javascript
import Echo from 'laravel-echo'

window.Echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: 443,
  wssPort: 443,
  forceTLS: true,
  enabledTransports: ['ws', 'wss'],
})

// Escuchar eventos
Echo.channel('show.1')
  .listen('PlayerEliminated', (e) => {
    console.log(`Player ${e.player.number} eliminated`)
  })
```

## Rate Limiting

```php
// routes/channels.php
Broadcast::channel('game.{type}', function ($user, $type) {
    return $user->status === 'alive';
}, ['throttle' => 'ws:10,1']); // 10 mensajes por segundo
```

## Docker + Traefik

Reverb usa **el mismo dominio y puerto** que Laravel (443/WSS).

```yaml
labels:
  - "traefik.enable=true"
  - "traefik.http.routers.api.rule=Host(`api.tudominio.com`)"
  - "traefik.http.services.api.loadbalancer.server.port=9000"
```

Traefik detecta automáticamente WebSocket upgrade.

---

## Nuevos Eventos Detallados (2025)

### Achievement Events

#### `AchievementUnlocked`
**Canal**: `private-player.{player_id}`

**Payload**:
```json
{
  "player_id": 123,
  "achievement_id": 5,
  "achievement_key": "perfect_millionaire",
  "name": "Millonario Perfecto",
  "description": "Respondiste todas las preguntas correctamente",
  "icon": "🏆",
  "points": 500,
  "rarity": "legendary"
}
```

**Frontend**: Toast celebratorio + sonido `achievement-unlocked.mp3`

#### `AchievementProgress`
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

**Frontend**: Progress bar silenciosa, no toast

---

### Instruction Events

#### `InstructionsRequired`
**Canal**: `game.show`

**Payload**:
```json
{
  "game_type": "millionaire",
  "instructions": {
    "id": 1,
    "title": "¿Quién Quiere Ser Millonario?",
    "content": "Responde preguntas de cultura general...",
    "duration_seconds": 30,
    "media_urls": ["https://s3.../instructions_millionaire.mp4"]
  }
}
```

**Frontend**: Modal `GameInstructions.vue` con countdown

#### `InstructionsCompleted`
**Canal**: `game.show`

**Payload**:
```json
{
  "game_type": "millionaire",
  "player_id": 45,
  "completed_by": "player|supervisor",
  "skipped": false
}
```

**Frontend**: Cerrar modal, continuar al juego

---

### Audio Tracking Events

#### `TrackStarted`
**Canal**: `game.show`

**Payload**:
```json
{
  "track_id": "millionaire_theme",
  "channel": "music",
  "url": "https://s3.../millionaire_theme.mp3",
  "duration_seconds": 120,
  "volume": 0.6
}
```

**Frontend**: `MusicBox.vue` actualiza UI (upper-left), inicia visualizer

#### `TrackEnded`
**Canal**: `game.show`

**Payload**:
```json
{
  "track_id": "millionaire_theme",
  "channel": "music",
  "reason": "completed|stopped|error"
}
```

**Frontend**: `MusicBox.vue` limpia UI

#### `VolumeChanged`
**Canal**: `game.show`

**Payload**:
```json
{
  "channel": "music|sfx|voice",
  "volume": 0.8,
  "changed_by": "supervisor|system"
}
```

**Frontend**: Ajustar Howler.js volume en canal específico

---

### Audit Events

#### `AuditLogCreated`
**Canal**: `private-supervisor` (solo supervisors)

**Payload**:
```json
{
  "log_id": 12345,
  "event_type": "supervisor_action",
  "actor": {
    "id": 1,
    "type": "supervisor",
    "name": "Admin Principal"
  },
  "target": {
    "id": 45,
    "type": "player",
    "name": "Jugador #45"
  },
  "context": {
    "action": "eliminate_player",
    "reason": "Violación de reglas"
  },
  "created_at": "2025-12-21T15:30:00Z"
}
```

**Frontend**: Live feed en supervisor dashboard, highlight críticos

---

### Bonus Game Events

#### `WordFound`
**Canal**: `game.word_search`

**Payload**:
```json
{
  "game_id": 78,
  "player_id": 23,
  "word_id": 5,
  "word": "COLOMBIA",
  "is_valid": true,
  "found_at": "2025-12-21T15:30:00Z",
  "time_elapsed_seconds": 45
}
```

**Frontend**: Highlight palabra en grid, sonido `word-found.mp3`

#### `FlappyCrashed`
**Canal**: `game.flappy`

**Payload**:
```json
{
  "game_id": 90,
  "player_id": 12,
  "survival_time_seconds": 67,
  "pipes_passed": 15,
  "crash_reason": "pipe_collision",
  "crashed_at": "2025-12-21T15:30:00Z"
}
```

**Frontend**: Explosión Phaser, overlay "Game Over", sonido `flappy-crash.mp3`

---

## Tabla de Canales y Eventos

| Canal | Eventos | Audiencia |
|-------|---------|-----------|
| `game.show` | PlayerJoined, PlayerEliminated, GameStarted, InstructionsRequired, TrackStarted, TrackEnded, VolumeChanged | Todos (público) |
| `private-player.{id}` | AchievementUnlocked, AchievementProgress, PlayerPassed | Jugador específico |
| `private-supervisor` | AuditLogCreated | Solo supervisors |
| `game.millionaire` | AnswerValidated | Jugadores vivos |
| `game.rope` | RopeStateUpdated | Jugadores vivos |
| `game.spell` | SpellValidated | Jugadores vivos |
| `game.roulette` | SpinResult | Jugadores finalistas |
| `game.word_search` | WordFound, WordSearchCompleted | Todos participantes |
| `game.flappy` | FlappyCrashed, FlappyScoreUpdated | Todos participantes |
| `chat` | ChatMessageSent | Todos (público) |

---

**Ver también**: 
- `achievement-system.md` - Detalles de achievement triggers
- `audit-system.md` - Lógica de auditoría y broadcasting
- `audio-system.md` - Sistema de 3 canales de audio
- `bonus-games.md` - Implementación Word Search y Flappy

**Última actualización**: Diciembre 2025
