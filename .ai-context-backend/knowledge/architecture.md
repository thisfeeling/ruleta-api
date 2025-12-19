# Architecture - Ruleta Familiar Backend

## Visión General

Ruleta Familiar sigue una arquitectura **servidor autoritativo** donde Laravel es la única fuente de verdad. El backend orquesta todo el show, valida acciones, calcula resultados, y sincroniza estado vía WebSockets (Reverb).

---

## Diagrama de Arquitectura General

```
┌─────────────────────────────────────────────────────────────┐
│                         INTERNET                             │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│                   Traefik (Proxy + SSL)                      │
│                 (Automático con Dokploy)                     │
└──────┬────────────────────────────────────┬─────────────────┘
       │                                    │
       │ HTTPS/WSS                          │ HTTPS
       │                                    │
┌──────▼────────────────────┐      ┌───────▼─────────────────┐
│   Laravel 12 + Reverb     │      │    Vue 3 SPA (Nginx)    │
│   (API + WebSockets)      │◄─────┤    (Archivos estáticos) │
└──────┬────────────────────┘      └─────────────────────────┘
       │
       │
┌──────▼────────┐  ┌─────────────┐  ┌──────────────────────┐
│  MySQL 8      │  │   Redis     │  │  RustFS (S3)         │
│  (BD)         │  │  (Cache/    │  │  (Audios)            │
│               │  │   Queue)    │  │                      │
└───────────────┘  └─────────────┘  └──────────────────────┘
```

---

## Principios Arquitectónicos

### 1. Servidor Autoritativo

**Regla de Oro**: Laravel decide TODO, Vue solo renderiza.

| Responsabilidad | Laravel | Vue |
|-----------------|---------|-----|
| Lógica de juego | ✅ | ❌ |
| Validación | ✅ | ❌ |
| Eliminaciones | ✅ | ❌ |
| Puntuaciones | ✅ | ❌ |
| Estado del show | ✅ | ❌ |
| Renderizado | ❌ | ✅ |
| Input | ❌ | ✅ |
| Animaciones | ❌ | ✅ |

**Ventajas**:
- Anti-trampas
- Consistencia garantizada
- Lógica centralizada
- Más fácil de debuggear

### 2. Event-Driven

Todo el sistema se comunica por **eventos**.

```php
// Laravel
event(new PlayerEliminated($player));

// Reverb broadcast automático

// Vue recibe
Echo.channel('show').listen('PlayerEliminated', (e) => {
  store.eliminatePlayer(e.player_id)
})
```

### 3. Desacoplamiento Frontend/Backend

- **Dos repositorios** independientes
- Comunicación **solo por API + WebSockets**
- Contratos claros (events, API responses)
- Frontend es reemplazable (Vue → React, mobile, etc.)

---

## Capas de la Arquitectura

### Capa 1: Presentación (API + WebSockets)

**HTTP API** (`routes/api.php`)
```php
POST /api/auth/login
POST /api/auth/register
GET  /api/player/{id}
POST /api/player/action
POST /api/chat/send
```

**WebSocket** (Reverb)
```php
// Broadcasting
Broadcast::channel('show.{id}', ...);
Broadcast::channel('player.{id}', ...);
Broadcast::channel('game.{type}', ...);
```

### Capa 2: Aplicación (Orquestación)

**State Machine**
```php
class ShowMachine {
  public function transition(ShowPhase $to): void
  public function getCurrentPhase(): ShowPhase
  public function canTransition(ShowPhase $to): bool
}
```

**Actions** (Casos de uso)
```php
app/Actions/
├── Player/
│   ├── AssignPlayerNumber.php
│   ├── EliminatePlayer.php
│   └── ReconnectPlayer.php
├── Game/
│   ├── StartGame.php
│   └── EndGame.php
└── Audio/
    └── PlaySystemAudio.php
```

### Capa 3: Dominio (Lógica de negocio)

**Entidades**
```php
app/Domain/
├── Player/
│   ├── Player.php
│   ├── PlayerStatus.php
│   └── PlayerRepository.php
├── Show/
│   ├── Show.php
│   ├── ShowState.php
│   └── ShowPhase.php
└── Game/
    ├── Millionaire/
    ├── Rope/
    ├── Spell/
    └── Roulette/
```

### Capa 4: Infraestructura (Servicios externos)

```php
app/Services/
├── Audio/
│   ├── TopMediaiService.php
│   ├── ElevenLabsService.php (fallback)
│   └── AudioStorageService.php
├── Game/
│   └── EliminationService.php
└── WebSocket/
    └── ReverBroadcaster.php
```

---

## Flujo de una Acción Típica

### Ejemplo: Jugador responde en "Millonario"

```
1. CLIENTE (Vue)
   user clicks "Respuesta A"
   ↓
   axios.post('/api/millionaire/answer', { answer: 'A' })

2. API CONTROLLER
   MillionaireController::answer()
   ↓
   Valida: ¿Está en cooldown? ¿Es su turno?
   ↓
   Dispara Action

3. ACTION
   ValidateAnswer::handle($player, $answer)
   ↓
   Calcula: correcto/incorrecto
   ↓
   Actualiza puntuación
   ↓
   Emite evento

4. EVENT
   AnswerValidated($player, $correct, $points)
   ↓
   Laravel Broadcasting

5. REVERB
   Broadcast a canal 'game.millionaire'
   ↓
   Todos los clientes conectados reciben

6. CLIENTE (Vue)
   Echo.listen('AnswerValidated', (e) => {
     if (e.player_id === me.id) {
       showResult(e.correct)
     }
     updateScoreboard(e.player_id, e.points)
   })
```

**Tiempo total**: < 200ms

---

## Patrones de Diseño Utilizados

### 1. Command Pattern (Actions)
```php
class EliminatePlayer {
  public function handle(Player $player): void {
    $player->eliminate();
    event(new PlayerEliminated($player));
  }
}
```

### 2. State Pattern (Show Machine)
```php
class ShowState {
  public function next(): ShowState
  public function canTransition(ShowState $to): bool
}
```

### 3. Observer Pattern (Events)
```php
event(new PlayerEliminated($player));

// Listeners
- UpdateStatCard
- BroadcastToClients
- SaveToLog
```

### 4. Repository Pattern (Data Access)
```php
interface PlayerRepository {
  public function findByNumber(int $number): ?Player
  public function findAlive(): Collection
}
```

### 5. Service Layer (Lógica compleja)
```php
class AudioService {
  public function generate(string $text): Audio
  public function store(Audio $audio): string
}
```

---

## Manejo de Estado

### Estado del Show

**Dónde vive**:
- **Inmediato**: Redis (cache)
- **Persistente**: MySQL (cada 30s)

**Qué incluye**:
```php
{
  "show_id": 1,
  "phase": "MILLIONAIRE",
  "round": 1,
  "players_alive": 25,
  "current_game_state": {...}
}
```

### Estado del Jugador

```php
{
  "id": 123,
  "nickname": "Player1",
  "number": 7,
  "status": "alive",
  "points": 150,
  "cooldown_until": null
}
```

### Estado del Juego Activo

Cada juego mantiene su propio estado:

**Millonario**:
```php
{
  "current_question": 5,
  "scoreboard": [...],
  "cooldowns": [...]
}
```

**Cuerda**:
```php
{
  "groups": [...],
  "tensions": [0.3, -0.5, ...],
  "time_remaining": 45
}
```

---

## WebSockets (Reverb)

### Arquitectura de Reverb

```
Laravel App (HTTP)
    ↓
Reverb Server (WS) ← mismo contenedor
    ↓
Redis (PubSub)
    ↓
Clientes (Echo)
```

### Canales

#### Públicos
```php
'show.{id}' → Estado general
'chat' → Chat global
```

#### Privados
```php
'player.{id}' → Notificaciones personales
'game.{type}' → Estado del juego actual
```

#### Presence
```php
'supervisor.show' → Lista de supervisores online
```

### Autorización

```php
Broadcast::channel('player.{id}', function ($user, $id) {
    return $user->id === (int) $id;
});

Broadcast::channel('supervisor.show', function ($user) {
    return $user->role === 'supervisor';
});
```

---

## Audio Pipeline

```
1. TRIGGER
   event(new PlayerEliminated($player))
   ↓
2. LISTENER
   PlayEliminationSound::handle()
   ↓
3. SERVICE
   AudioService::get('eliminated')
   ↓
4. CACHE CHECK
   Redis: audio:eliminated → URL?
   ↓
   YES → Return URL
   NO → Continue
   ↓
5. STORAGE
   S3: audios/eliminated.mp3
   ↓
6. SIGN URL
   RustFS::temporaryUrl(24h)
   ↓
7. BROADCAST
   AudioRequested($url)
   ↓
8. CLIENT
   audioService.play(url)
```

---

## Seguridad

### Rate Limiting

```php
// routes/api.php
Route::middleware('throttle:actions')->group(function () {
  Route::post('/millionaire/answer', ...);
  Route::post('/chat/send', ...);
});

// config
'actions' => [
  'limit' => 10,
  'every' => 1, // segundo
],
```

### Validación de Acciones

```php
// Antes de ejecutar cualquier acción
if ($player->status !== 'alive') {
  abort(403, 'No puedes jugar si estás eliminado');
}

if ($game->phase !== 'MILLIONAIRE') {
  abort(400, 'Acción inválida para esta fase');
}
```

### Autorización WebSocket

```php
// Laravel genera token
$token = $user->createToken('ws')->plainTextToken;

// Vue lo usa
Echo.connector.options.auth = {
  headers: { Authorization: `Bearer ${token}` }
};
```

---

## Escalabilidad

### Horizontal Scaling (Futuro)

```
Traefik
   │
   ├─► Laravel Instance 1
   ├─► Laravel Instance 2
   └─► Laravel Instance 3
        │
        └─► Redis (shared)
```

**Importante**: Reverb soporta múltiples instancias con Redis como PubSub.

### Vertical Scaling (Actual)

- Aumentar RAM/CPU del contenedor
- Optimizar queries (eager loading)
- Cachear estado en Redis

---

## Monitoreo

### Métricas Clave

```php
// ¿Cuántos jugadores vivos?
Player::where('status', 'alive')->count();

// ¿Cuántos eventos/segundo?
Redis::get('metrics:events_per_second');

// ¿Latencia promedio WS?
Redis::hget('metrics:ws_latency', 'avg');
```

### Logs

```php
Log::channel('game')->info('Player eliminated', [
  'player_id' => $player->id,
  'game' => 'millionaire',
  'reason' => 'timeout',
]);
```

---

## Testing

### Unitarios
```php
test('player can be eliminated', function () {
  $player = Player::factory()->create();
  
  $player->eliminate();
  
  expect($player->status)->toBe('eliminated');
  expect($player->eliminated_at)->not->toBeNull();
});
```

### Integración
```php
test('millionaire answer triggers event', function () {
  Event::fake();
  
  $this->post('/api/millionaire/answer', ['answer' => 'A']);
  
  Event::assertDispatched(AnswerValidated::class);
});
```

### WebSocket
```php
test('player receives elimination event', function () {
  $player = Player::factory()->create();
  
  event(new PlayerEliminated($player));
  
  // Assert broadcast
});
```

---

## Decisiones Arquitectónicas Documentadas

### ¿Por qué Reverb y no Pusher?

- ✅ Gratis
- ✅ Control total
- ✅ Mismo contenedor
- ✅ No vendor lock-in
- ❌ Menos maduro (aceptable para uso familiar)

### ¿Por qué servidor autoritativo?

- ✅ Anti-trampas
- ✅ Consistencia
- ✅ Más fácil de debuggear
- ❌ Latencia ligeramente mayor (acceptable <200ms)

### ¿Por qué MySQL y no PostgreSQL?

- ✅ Más familiar
- ✅ Suficiente para el caso de uso
- ✅ Mejor soporte en hosting barato
- ❌ Menos features avanzados (no necesarios)

### ¿Por qué supervisión humana en Deletréalo?

- ✅ Más preciso que speech-to-text
- ✅ Experiencia familiar (supervisores participan)
- ✅ Evita bugs de reconocimiento automático
- ❌ No escala a miles de jugadores (no es el objetivo)

---

## Próximos Pasos Arquitectónicos

1. **Implementar State Machine** completa
2. **Definir todos los eventos** Reverb
3. **Crear contratos** (interfaces) para servicios
4. **Tests** de integración
5. **Optimizar** queries con eager loading

---

**Ver también**:
- `reverb-websockets.md` - Detalles de Reverb
- `audio-system.md` - Pipeline de audio
- `database-schema.md` - Esquema completo
- `backend-structure.md` - Organización de carpetas
