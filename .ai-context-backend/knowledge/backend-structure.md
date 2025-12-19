# Backend Structure - Ruleta Familiar

## Estructura Completa del Backend

```
ruleta-api/
├── app/
│   ├── Actions/                    # Casos de uso atómicos
│   │   ├── Player/
│   │   │   ├── AssignPlayerNumber.php
│   │   │   ├── GenerateReconnectCode.php
│   │   │   ├── ReconnectPlayer.php
│   │   │   └── EliminatePlayer.php
│   │   ├── Audio/
│   │   │   ├── PlaySystemAudio.php
│   │   │   └── PlayNumberAudio.php
│   │   ├── Game/
│   │   │   ├── StartGame.php
│   │   │   ├── EndGame.php
│   │   │   └── TransitionPhase.php
│   │   └── Screens/
│   │       ├── ShowPassedScreen.php
│   │       └── ShowEliminatedScreen.php
│   │
│   ├── Domain/                     # Entidades y lógica de negocio
│   │   ├── Player/
│   │   │   ├── Player.php
│   │   │   ├── PlayerStatus.php (enum)
│   │   │   ├── PlayerRole.php (enum)
│   │   │   └── PlayerRepository.php
│   │   ├── Show/
│   │   │   ├── Show.php
│   │   │   ├── ShowState.php
│   │   │   ├── ShowPhase.php (enum)
│   │   │   └── ShowMachine.php
│   │   ├── Audio/
│   │   │   ├── SystemAudio.php
│   │   │   ├── NumberAudio.php
│   │   │   ├── SpellAudio.php
│   │   │   └── AudioContext.php (enum)
│   │   ├── Game/
│   │   │   ├── Millionaire/
│   │   │   │   ├── Question.php
│   │   │   │   ├── Answer.php
│   │   │   │   └── Scoreboard.php
│   │   │   ├── Rope/
│   │   │   │   ├── Group.php
│   │   │   │   └── Tension.php
│   │   │   ├── Spell/
│   │   │   │   ├── Word.php
│   │   │   │   └── Submission.php
│   │   │   └── Roulette/
│   │   │       ├── Spin.php
│   │   │       └── Sector.php
│   │   └── Screen/
│   │       ├── ScreenType.php (enum)
│   │       └── ScreenPayload.php
│   │
│   ├── Events/                     # Eventos para broadcasting
│   │   ├── PlayerJoined.php
│   │   ├── PlayerReconnected.php
│   │   ├── PlayerEliminated.php
│   │   ├── PlayerPassed.php
│   │   ├── ShowStateChanged.php
│   │   ├── GameStarted.php
│   │   ├── GameEnded.php
│   │   ├── ScreenChanged.php
│   │   ├── AudioRequested.php
│   │   ├── NumberCalled.php
│   │   ├── ChatMessageSent.php
│   │   └── Games/
│   │       ├── Millionaire/
│   │       │   ├── QuestionDisplayed.php
│   │       │   └── AnswerValidated.php
│   │       ├── Rope/
│   │       │   ├── RopeStateUpdated.php
│   │       │   └── GroupEliminated.php
│   │       ├── Spell/
│   │       │   ├── PlayerSelected.php
│   │       │   └── SpellValidated.php
│   │       └── Roulette/
│   │           └── SpinResult.php
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── PlayerController.php
│   │   │   ├── SupervisorController.php
│   │   │   ├── ChatController.php
│   │   │   ├── AudioController.php
│   │   │   └── Games/
│   │   │       ├── MillionaireController.php
│   │   │       ├── RopeController.php
│   │   │       ├── SpellController.php
│   │   │       └── RouletteController.php
│   │   ├── Middleware/
│   │   │   ├── RoleMiddleware.php
│   │   │   ├── PlayerAliveMiddleware.php
│   │   │   └── GamePhaseMiddleware.php
│   │   └── Requests/
│   │       ├── PlayerJoinRequest.php
│   │       ├── ReconnectRequest.php
│   │       └── ChatMessageRequest.php
│   │
│   ├── Services/                   # Servicios complejos
│   │   ├── Audio/
│   │   │   ├── TopMediaiService.php
│   │   │   ├── ElevenLabsService.php
│   │   │   ├── AudioStorageService.php
│   │   │   ├── AudioRegistryService.php
│   │   │   └── AudioDispatcher.php
│   │   ├── Game/
│   │   │   ├── EliminationService.php
│   │   │   ├── GroupingService.php (para La Cuerda)
│   │   │   └── StateMachineService.php
│   │   └── Player/
│   │       └── ReconnectionService.php
│   │
│   ├── Jobs/                       # Trabajos asíncronos
│   │   ├── GenerateSystemAudios.php
│   │   ├── GenerateNumberAudios.php
│   │   ├── StoreAudioInS3.php
│   │   └── ProcessSpellAudio.php
│   │
│   ├── Policies/                   # Autorización
│   │   ├── PlayerPolicy.php
│   │   └── AudioPolicy.php
│   │
│   └── Support/                    # Helpers y utilidades
│       ├── RandomNumberGenerator.php
│       ├── PinGenerator.php
│       └── CooldownManager.php
│
├── bootstrap/
│   ├── app.php
│   └── providers.php
│
├── config/
│   ├── app.php
│   ├── auth.php
│   ├── broadcasting.php           # Reverb config
│   ├── database.php
│   ├── filesystems.php            # RustFS config
│   ├── queue.php
│   └── services.php               # APIs externas
│
├── database/
│   ├── factories/
│   │   ├── PlayerFactory.php
│   │   ├── ShowFactory.php
│   │   └── QuestionFactory.php
│   ├── migrations/
│   │   ├── 2025_01_01_000001_create_players_table.php
│   │   ├── 2025_01_01_000002_create_shows_table.php
│   │   ├── 2025_01_01_000003_create_system_audios_table.php
│   │   ├── 2025_01_01_000004_create_number_audios_table.php
│   │   ├── 2025_01_01_000005_create_spell_audios_table.php
│   │   ├── 2025_01_01_000006_create_eliminations_table.php
│   │   ├── 2025_01_01_000007_create_questions_table.php
│   │   └── 2025_01_01_000008_create_chat_messages_table.php
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── SystemAudioSeeder.php
│       ├── NumberAudioSeeder.php
│       └── QuestionSeeder.php
│
├── routes/
│   ├── api.php                    # Rutas HTTP
│   ├── channels.php               # Canales WebSocket
│   ├── console.php
│   └── web.php
│
├── storage/
│   ├── app/
│   │   ├── public/
│   │   └── temp/                 # Audios temporales antes de S3
│   ├── logs/
│   └── framework/
│
├── tests/
│   ├── Feature/
│   │   ├── Auth/
│   │   ├── Player/
│   │   ├── Games/
│   │   └── WebSocket/
│   └── Unit/
│       ├── Domain/
│       ├── Services/
│       └── Actions/
│
├── docker/
│   ├── laravel/
│   │   ├── Dockerfile
│   │   └── supervisord.conf      # PHP-FPM + Reverb
│   └── queue/
│       └── Dockerfile
│
├── .env.example
├── .gitignore
├── artisan
├── composer.json
├── composer.lock
├── docker-compose.yml
├── phpunit.xml
└── README.md
```

---

## Explicación de Cada Directorio

### `app/Actions/`
Contiene **casos de uso específicos** que orquestan lógica.

**Características**:
- Atómicos (hacen UNA cosa)
- Reutilizables
- Inyectables

**Ejemplo**:
```php
class EliminatePlayer {
  public function handle(Player $player): void {
    $player->status = PlayerStatus::ELIMINATED;
    $player->eliminated_at = now();
    $player->save();
    
    event(new PlayerEliminated($player));
  }
}
```

### `app/Domain/`
Contiene **entidades** y **lógica de negocio pura**.

**Reglas**:
- NO debe depender de HTTP
- NO debe depender de Eloquent directamente
- Lógica reutilizable

**Ejemplo**:
```php
enum PlayerStatus: string {
  case ALIVE = 'alive';
  case ELIMINATED = 'eliminated';
  case SPECTATOR = 'spectator';
}
```

### `app/Events/`
Eventos que se **broadcastean** vía Reverb.

**Estructura típica**:
```php
class PlayerEliminated implements ShouldBroadcast {
  public function __construct(public Player $player) {}
  
  public function broadcastOn() {
    return new Channel('show.'.$this->player->show_id);
  }
}
```

### `app/Http/Controllers/`
Controladores HTTP que **validan** y **delegan** a Actions.

**Regla**: Delgados, no contienen lógica compleja.

### `app/Services/`
Servicios complejos que **no encajan** en Actions ni Domain.

**Ejemplo**: Integración con APIs externas (ElevenLabs, TopMediai).

### `app/Jobs/`
Trabajos **asíncronos** para tareas pesadas.

**Ejemplo**: Generar audios, procesar uploads, enviar notificaciones masivas.

### `database/migrations/`
Migraciones de BD en **orden cronológico**.

**Convención**: `YYYY_MM_DD_HHMMSS_descripcion.php`

### `database/seeders/`
Datos iniciales y **precarga de audios**.

**Crítico**: `SystemAudioSeeder` y `NumberAudioSeeder` deben ejecutarse UNA vez en setup.

---

## Namespaces

```php
App\Actions\Player\EliminatePlayer
App\Domain\Player\Player
App\Events\PlayerEliminated
App\Http\Controllers\PlayerController
App\Services\Audio\TopMediaiService
App\Jobs\GenerateSystemAudios
```

---

## Flujo Típico de Request

```
1. Request llega a route
   POST /api/millionaire/answer

2. Middleware valida
   - Autenticación (Sanctum)
   - Rol (PlayerAliveMiddleware)
   - Rate limiting

3. Controller recibe
   MillionaireController::answer(Request $request)

4. Valida input
   $validated = $request->validate([...])

5. Delega a Action
   app(ValidateAnswer::class)->handle($player, $validated)

6. Action ejecuta lógica
   - Calcula resultado
   - Actualiza estado
   - Emite evento

7. Event se broadcastea
   PlayerAnswered → Reverb → Todos los clientes

8. Response
   return response()->json(['success' => true])
```

---

## Convenciones de Código

### Nombres de Clases

| Tipo | Ejemplo |
|------|---------|
| Action | `EliminatePlayer` |
| Event | `PlayerEliminated` |
| Service | `TopMediaiService` |
| Controller | `PlayerController` |
| Request | `PlayerJoinRequest` |
| Job | `GenerateSystemAudios` |

### Métodos

| Tipo | Ejemplo |
|------|---------|
| Action | `handle()` |
| Controller | `index()`, `store()`, `update()`, `destroy()` |
| Service | Verbos (`generate()`, `store()`, `fetch()`) |

### Propiedades

```php
// Públicas en Events (para broadcasting)
public Player $player;

// Privadas/protected en Services
protected TopMediaiClient $client;

// Tipadas siempre
public function handle(Player $player): void
```

---

## Organización por Features (Alternativa)

Si el proyecto crece, considera agrupar por **feature**:

```
app/Features/
├── Authentication/
├── Player/
├── Games/
│   ├── Millionaire/
│   ├── Rope/
│   ├── Spell/
│   └── Roulette/
└── Supervision/
```

**Nota**: Para MVP, estructura por tipo (Actions, Events, etc.) es suficiente.

---

## Archivos de Configuración Críticos

### `.env`
```env
APP_NAME="Ruleta Familiar"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.tudominio.com

DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=ruleta
DB_USERNAME=ruleta
DB_PASSWORD=secret

CACHE_STORE=redis
QUEUE_CONNECTION=redis

BROADCAST_CONNECTION=reverb

REVERB_APP_ID=ruleta
REVERB_APP_KEY=localkey
REVERB_APP_SECRET=localsecret
REVERB_HOST=api.tudominio.com
REVERB_PORT=443
REVERB_SCHEME=https

RUSTFS_KEY=your_key
RUSTFS_SECRET=your_secret
RUSTFS_REGION=us-east-1
RUSTFS_BUCKET=ruleta-audios
RUSTFS_ENDPOINT=https://rustfs.example.com

TOPMEDIAI_API_KEY=your_api_key
ELEVENLABS_API_KEY=your_backup_key
```

### `config/broadcasting.php`
```php
'reverb' => [
    'driver' => 'reverb',
    'key' => env('REVERB_APP_KEY'),
    'secret' => env('REVERB_APP_SECRET'),
    'app_id' => env('REVERB_APP_ID'),
    'options' => [
        'host' => env('REVERB_HOST', '127.0.0.1'),
        'port' => env('REVERB_PORT', 443),
        'scheme' => env('REVERB_SCHEME', 'https'),
    ],
],
```

### `config/filesystems.php`
```php
'rustfs' => [
    'driver' => 's3',
    'key' => env('RUSTFS_KEY'),
    'secret' => env('RUSTFS_SECRET'),
    'region' => env('RUSTFS_REGION'),
    'bucket' => env('RUSTFS_BUCKET'),
    'endpoint' => env('RUSTFS_ENDPOINT'),
    'use_path_style_endpoint' => true,
],
```

---

## Scripts Útiles

### `composer.json` - Scripts
```json
{
  "scripts": {
    "setup": [
      "@php artisan key:generate",
      "@php artisan migrate --seed",
      "@php artisan reverb:install"
    ],
    "test": "@php artisan test",
    "format": "@php vendor/bin/pint"
  }
}
```

### Artisan Commands Personalizados
```bash
php artisan game:start       # Iniciar show
php artisan game:reset       # Resetear estado
php artisan audio:generate   # Generar audios precargados
php artisan players:cleanup  # Limpiar jugadores inactivos
```

---

## Testing

### Estructura de Tests
```
tests/
├── Feature/
│   ├── PlayerTest.php
│   ├── MillionaireTest.php
│   └── WebSocketTest.php
└── Unit/
    ├── EliminationServiceTest.php
    └── ShowMachineTest.php
```

### Ejemplo de Test
```php
it('eliminates player when timeout', function () {
  $player = Player::factory()->create();
  
  $action = app(EliminatePlayer::class);
  $action->handle($player);
  
  expect($player->fresh()->status)->toBe('eliminated');
});
```

---

## Próximos Pasos

1. **Crear migraciones** completas
2. **Implementar models** básicos
3. **Definir eventos** principales
4. **Crear seeders** de audios
5. **Implementar State Machine**

---

**Ver también**:
- `database-schema.md` - Esquema completo de BD
- `architecture.md` - Arquitectura general
- `rules/game-implementation.md` - Lógica de cada juego
