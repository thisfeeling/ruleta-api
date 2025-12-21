# Audit System - Backend

## Descripción General

Sistema de auditoría del backend que registra **todas** las acciones críticas del sistema con dual storage: base de datos MySQL para consultas rápidas (últimos 30 días) y almacenamiento permanente en RustFS S3 para archivado histórico. Diseñado para cumplir con requisitos de trazabilidad, debugging y análisis forense.

## Características Principales

- **Dual Storage Strategy**: DB (30 días) + S3 (permanente, ilimitado)
- **Event-Driven**: Logs automáticos via observers y listeners
- **Structured Data**: JSON schema para contextos complejos
- **Real-Time Dashboard**: Supervisor UI con WebSocket updates
- **Privacy-Aware**: IP y user_agent para auditoría sin PII excesivo
- **Queryable**: Filtros avanzados (date range, actor, event type, target)
- **Auto-Archiving**: Job diario que mueve logs >30d a S3
- **Performance Optimized**: Particionamiento por fecha, índices compuestos

## Arquitectura

```
┌────────────────────────────────────────────────────────┐
│               Event Occurs in System                    │
│  (ej: Jugador responde pregunta, Supervisor elimina)   │
└──────────────────┬─────────────────────────────────────┘
                   │
                   v
┌────────────────────────────────────────────────────────┐
│          AuditService::log($eventType, $data)          │
│  - Valida event_type                                    │
│  - Extrae actor y target                                │
│  - Serializa context a JSON                             │
│  - Captura IP y user_agent                              │
└──────────────────┬─────────────────────────────────────┘
                   │
                   v
┌────────────────────────────────────────────────────────┐
│          INSERT INTO audit_logs (DB)                    │
│  - Escribe en MySQL con índices optimizados             │
│  - Partición: created_at por mes                        │
└──────────────────┬─────────────────────────────────────┘
                   │
         ┌─────────┴──────────┐
         │ ¿Es crítico?        │
         └─────────┬───────────┘
                   │
        ┌──────────┴──────────┐
        │ SI                  │ NO (async)
        v                     v
┌─────────────────┐   ┌──────────────────────────────────┐
│  Broadcast      │   │  Continuar                       │
│  AuditLogCreated│   │  (no broadcast)                  │
│  (supervisor UI)│   └──────────────────────────────────┘
└────────┬────────┘
         │
         v
┌────────────────────────────────────────────────────────┐
│               Supervisor Dashboard                      │
│  - Live feed de eventos críticos                        │
│  - Filtros en tiempo real                               │
│  - Export a CSV/JSON                                    │
└────────────────────────────────────────────────────────┘

[DAILY JOB 2:00 AM]
┌────────────────────────────────────────────────────────┐
│        ArchiveOldAuditLogs Job                          │
│  1. SELECT logs WHERE created_at < NOW() - 30 days     │
│  2. COMPRESS to JSON.gz                                 │
│  3. UPLOAD to S3: audits/{year}/{month}/{day}.json.gz  │
│  4. DELETE from MySQL                                   │
└────────────────────────────────────────────────────────┘
```

## Esquema de Base de Datos

### Tabla: `audit_logs`

Registros de auditoría con particionamiento mensual.

```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Tipo de evento
    event_type VARCHAR(100) NOT NULL COMMENT 'player_action, supervisor_action, game_state_change, system_event, audio_played, achievement_awarded',
    
    -- Actor (quien ejecuta la acción)
    actor_id BIGINT UNSIGNED NULL COMMENT 'ID del actor (player o supervisor)',
    actor_type ENUM('player', 'supervisor', 'system') NOT NULL,
    
    -- Target (sobre quién/qué se ejecuta)
    target_id BIGINT UNSIGNED NULL COMMENT 'ID del target (player, game, achievement, etc)',
    target_type VARCHAR(50) NULL COMMENT 'player, game, achievement, audio, instruction',
    
    -- Contexto estructurado
    context JSON NOT NULL COMMENT 'Datos específicos del evento (flexible schema)',
    
    -- Metadatos de red
    ip_address VARCHAR(45) NULL COMMENT 'IPv4 o IPv6 del actor',
    user_agent TEXT NULL COMMENT 'User agent del browser',
    
    -- Timestamp
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices para queries frecuentes
    INDEX idx_event_type (event_type),
    INDEX idx_actor (actor_type, actor_id),
    INDEX idx_target (target_type, target_id),
    INDEX idx_created_at (created_at),
    INDEX idx_composite_query (event_type, actor_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
PARTITION BY RANGE (UNIX_TIMESTAMP(created_at)) (
    PARTITION p202512 VALUES LESS THAN (UNIX_TIMESTAMP('2026-01-01')),
    PARTITION p202601 VALUES LESS THAN (UNIX_TIMESTAMP('2026-02-01')),
    PARTITION p202602 VALUES LESS THAN (UNIX_TIMESTAMP('2026-03-01')),
    -- ... (auto-creadas por job mensual)
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

**Nota sobre particionamiento**: MySQL requiere que la columna de particionamiento (`created_at`) forme parte de todas las claves únicas. Por eso `id` no tiene UNIQUE constraint global, pero cada partición mantiene integridad.

### Context JSON Schema por Event Type

#### `player_action`
```json
{
    "action": "answer_question|vote_rope|spell_word|spin_roulette|find_word|flap",
    "game_type": "millionaire|rope|spell|roulette|word_search|flappy",
    "game_id": 123,
    "details": {
        "question_id": 45,
        "selected_answer": "B",
        "is_correct": true,
        "time_taken_ms": 3500
    }
}
```

#### `supervisor_action`
```json
{
    "action": "validate_audio|eliminate_player|skip_instructions|start_game|pause_game|override_score",
    "game_type": "spell",
    "game_id": 78,
    "details": {
        "player_id": 12,
        "audio_url": "https://s3.../spell_audio_12_78.mp3",
        "validation_result": "approved|rejected",
        "reason": "Pronunciación clara y correcta"
    }
}
```

#### `game_state_change`
```json
{
    "from_state": "waiting|playing|completed",
    "to_state": "playing|completed|cancelled",
    "game_type": "millionaire",
    "game_id": 56,
    "details": {
        "remaining_players": 25,
        "eliminated_count": 10
    }
}
```

#### `system_event`
```json
{
    "event": "show_started|show_ended|reconnection|error",
    "details": {
        "total_players": 50,
        "duration_minutes": 120,
        "winner_id": 7
    }
}
```

#### `audio_played`
```json
{
    "audio_type": "music|sfx|voice",
    "track_name": "millionaire_theme.mp3",
    "channel": "music|sfx|voice",
    "volume": 0.6,
    "duration_seconds": 120,
    "triggered_by": "game_start",
    "url": "https://s3.../millionaire_theme.mp3"
}
```

#### `achievement_awarded`
```json
{
    "achievement_id": 5,
    "achievement_key": "perfect_millionaire",
    "points": 500,
    "rarity": "legendary",
    "trigger": "OnNoErrorsRun"
}
```

## Service: `AuditService.php`

### Ubicación

```
app/Services/Audit/AuditService.php
```

### Responsabilidades

1. **Registrar eventos**: Método principal `log()`
2. **Consultar logs**: Queries con filtros complejos
3. **Archivar a S3**: Compresión y upload
4. **Purgar antiguos**: Limpieza de DB después de archivar
5. **Broadcasting**: Eventos críticos a supervisor dashboard

### Métodos Principales

#### `log(string $eventType, array $data): AuditLog`

Registra un evento de auditoría.

```php
<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Events\AuditLogCreated;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Registra un evento de auditoría
     *
     * @param string $eventType Tipo de evento (player_action, supervisor_action, etc)
     * @param array $data Datos del evento con keys: actor_id, actor_type, target_id, target_type, context
     * @return AuditLog
     */
    public static function log(string $eventType, array $data): AuditLog
    {
        // 1. Validar event_type
        $validEventTypes = [
            'player_action',
            'supervisor_action',
            'game_state_change',
            'system_event',
            'audio_played',
            'achievement_awarded'
        ];
        
        if (!in_array($eventType, $validEventTypes)) {
            throw new \InvalidArgumentException("Invalid event_type: {$eventType}");
        }
        
        // 2. Crear registro
        $auditLog = AuditLog::create([
            'event_type' => $eventType,
            'actor_id' => $data['actor_id'] ?? null,
            'actor_type' => $data['actor_type'] ?? 'system',
            'target_id' => $data['target_id'] ?? null,
            'target_type' => $data['target_type'] ?? null,
            'context' => $data['context'] ?? [],
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent()
        ]);
        
        // 3. Broadcast si es crítico
        if (self::isCriticalEvent($eventType, $data)) {
            broadcast(new AuditLogCreated($auditLog))->toOthers();
        }
        
        return $auditLog;
    }
    
    /**
     * Determina si un evento es crítico (requiere broadcast)
     */
    protected static function isCriticalEvent(string $eventType, array $data): bool
    {
        $criticalEvents = [
            'supervisor_action',
            'game_state_change',
            'system_event'
        ];
        
        if (in_array($eventType, $criticalEvents)) {
            return true;
        }
        
        // Achievement legendary
        if ($eventType === 'achievement_awarded' && ($data['context']['rarity'] ?? '') === 'legendary') {
            return true;
        }
        
        return false;
    }
}
```

#### `query(array $filters): Collection`

Consulta logs con filtros avanzados.

```php
/**
 * Consulta logs con filtros
 *
 * @param array $filters Filtros: event_type, actor_type, actor_id, target_type, target_id, date_from, date_to, limit
 * @return \Illuminate\Database\Eloquent\Collection
 */
public static function query(array $filters): Collection
{
    $query = AuditLog::query();
    
    // Event type
    if (isset($filters['event_type'])) {
        $query->where('event_type', $filters['event_type']);
    }
    
    // Actor
    if (isset($filters['actor_type'])) {
        $query->where('actor_type', $filters['actor_type']);
    }
    if (isset($filters['actor_id'])) {
        $query->where('actor_id', $filters['actor_id']);
    }
    
    // Target
    if (isset($filters['target_type'])) {
        $query->where('target_type', $filters['target_type']);
    }
    if (isset($filters['target_id'])) {
        $query->where('target_id', $filters['target_id']);
    }
    
    // Date range
    if (isset($filters['date_from'])) {
        $query->where('created_at', '>=', $filters['date_from']);
    }
    if (isset($filters['date_to'])) {
        $query->where('created_at', '<=', $filters['date_to']);
    }
    
    // Context search (JSON)
    if (isset($filters['context_search'])) {
        $query->whereRaw("JSON_SEARCH(context, 'one', ?) IS NOT NULL", [$filters['context_search']]);
    }
    
    // Order and limit
    $query->orderBy('created_at', 'desc');
    
    if (isset($filters['limit'])) {
        $query->limit($filters['limit']);
    }
    
    return $query->get();
}
```

#### `archive(Carbon $beforeDate): int`

Archiva logs antiguos a S3.

```php
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

/**
 * Archiva logs antiguos a S3
 *
 * @param Carbon $beforeDate Fecha límite (logs anteriores serán archivados)
 * @return int Cantidad de logs archivados
 */
public static function archive(Carbon $beforeDate): int
{
    // 1. Obtener logs antiguos en chunks
    $archived = 0;
    $chunkSize = 1000;
    
    AuditLog::where('created_at', '<', $beforeDate)
        ->orderBy('created_at')
        ->chunk($chunkSize, function ($logs) use (&$archived) {
            // Agrupar por día
            $byDay = $logs->groupBy(fn($log) => $log->created_at->format('Y-m-d'));
            
            foreach ($byDay as $date => $dayLogs) {
                // 2. Preparar datos
                $data = $dayLogs->map(fn($log) => [
                    'id' => $log->id,
                    'event_type' => $log->event_type,
                    'actor_id' => $log->actor_id,
                    'actor_type' => $log->actor_type,
                    'target_id' => $log->target_id,
                    'target_type' => $log->target_type,
                    'context' => $log->context,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'created_at' => $log->created_at->toIso8601String()
                ])->toArray();
                
                // 3. Comprimir JSON
                $json = json_encode($data, JSON_PRETTY_PRINT);
                $compressed = gzencode($json, 9);
                
                // 4. Upload a S3
                $year = Carbon::parse($date)->year;
                $month = Carbon::parse($date)->format('m');
                $day = Carbon::parse($date)->format('d');
                $path = "audits/{$year}/{$month}/{$day}.json.gz";
                
                Storage::disk('s3')->put($path, $compressed);
                
                // 5. Eliminar de DB
                AuditLog::whereIn('id', $dayLogs->pluck('id'))->delete();
                
                $archived += $dayLogs->count();
            }
        });
    
    return $archived;
}
```

#### `purgeOld(int $daysToKeep = 30): int`

Elimina logs viejos que ya fueron archivados.

```php
/**
 * Elimina logs antiguos (deben estar archivados en S3)
 *
 * @param int $daysToKeep Días a mantener en DB (default: 30)
 * @return int Logs eliminados
 */
public static function purgeOld(int $daysToKeep = 30): int
{
    $cutoffDate = now()->subDays($daysToKeep);
    
    // Verificar que estén archivados en S3
    $deleted = 0;
    
    AuditLog::where('created_at', '<', $cutoffDate)
        ->chunk(1000, function ($logs) use (&$deleted) {
            // Validar que existan en S3
            foreach ($logs->groupBy(fn($log) => $log->created_at->format('Y-m-d')) as $date => $dayLogs) {
                $year = Carbon::parse($date)->year;
                $month = Carbon::parse($date)->format('m');
                $day = Carbon::parse($date)->format('d');
                $path = "audits/{$year}/{$month}/{$day}.json.gz";
                
                if (Storage::disk('s3')->exists($path)) {
                    AuditLog::whereIn('id', $dayLogs->pluck('id'))->delete();
                    $deleted += $dayLogs->count();
                } else {
                    \Log::warning("Archivo S3 no existe, no se eliminan logs: {$path}");
                }
            }
        });
    
    return $deleted;
}
```

## Job: `ArchiveOldAuditLogs`

Job diario que se ejecuta a las 2:00 AM para archivar logs >30 días.

### Ubicación

```
app/Jobs/ArchiveOldAuditLogs.php
```

### Implementación

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Audit\AuditService;
use Carbon\Carbon;

class ArchiveOldAuditLogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $timeout = 3600; // 1 hora
    
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cutoffDate = now()->subDays(30);
        
        \Log::info("Iniciando archivado de audit logs anteriores a {$cutoffDate}");
        
        try {
            // 1. Archivar a S3
            $archived = AuditService::archive($cutoffDate);
            
            // 2. Purgar de DB (solo los archivados exitosamente)
            $purged = AuditService::purgeOld(30);
            
            \Log::info("Archivado completado: {$archived} logs archivados, {$purged} logs purgados de DB");
            
            // 3. Log de auditoría del propio archivado
            AuditService::log('system_event', [
                'actor_type' => 'system',
                'context' => [
                    'event' => 'audit_logs_archived',
                    'archived_count' => $archived,
                    'purged_count' => $purged,
                    'cutoff_date' => $cutoffDate->toIso8601String()
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error("Error archivando audit logs: {$e->getMessage()}");
            throw $e;
        }
    }
}
```

### Schedule en `app/Console/Kernel.php`

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->job(new ArchiveOldAuditLogs)
        ->dailyAt('02:00')
        ->timezone('America/Bogota')
        ->withoutOverlapping()
        ->onOneServer();
}
```

## Eventos WebSocket

### `AuditLogCreated`

Notifica al supervisor dashboard cuando ocurre un evento crítico.

**Canal**: `private-supervisor`

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

**Integración Frontend**:
- Live feed en supervisor dashboard
- Highlight de eventos críticos
- Filtros en tiempo real

## Endpoints API

### `GET /api/supervisor/audit-logs`

Lista logs con filtros (solo supervisor).

**Query params**:
- `event_type`: string
- `actor_type`: 'player'|'supervisor'|'system'
- `actor_id`: int
- `target_type`: string
- `target_id`: int
- `date_from`: ISO8601 string
- `date_to`: ISO8601 string
- `context_search`: string (búsqueda en JSON)
- `limit`: int (default: 100, max: 1000)
- `page`: int

**Response**:
```json
{
    "data": [
        {
            "id": 12345,
            "event_type": "player_action",
            "actor": {
                "id": 23,
                "type": "player",
                "name": "Jugador #23"
            },
            "target": {
                "id": 78,
                "type": "game",
                "name": "Millonario #78"
            },
            "context": {
                "action": "answer_question",
                "question_id": 5,
                "selected_answer": "B",
                "is_correct": true
            },
            "ip_address": "192.168.1.100",
            "created_at": "2025-12-21T15:30:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "total": 1234,
        "per_page": 100
    }
}
```

### `GET /api/supervisor/audit-logs/{id}`

Detalle de un log específico.

### `POST /api/supervisor/audit-logs/export`

Exporta logs filtrados a CSV o JSON.

**Body**:
```json
{
    "format": "csv|json",
    "filters": {
        "date_from": "2025-12-01T00:00:00Z",
        "date_to": "2025-12-21T23:59:59Z",
        "event_type": "supervisor_action"
    }
}
```

**Response**: Download file

## Integración con Otros Sistemas

### 1. Achievement System

Cada logro otorgado se audita:

```php
// En AchievementService::awardAchievement()
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

### 2. Audio System

Cada audio reproducido se audita:

```php
// En AudioController::play()
AuditService::log('audio_played', [
    'actor_id' => $player->id ?? null,
    'actor_type' => $player ? 'player' : 'system',
    'target_id' => $audio->id,
    'target_type' => 'audio',
    'context' => [
        'audio_type' => $audio->type,
        'track_name' => $audio->filename,
        'channel' => $channel,
        'volume' => $volume,
        'duration_seconds' => $audio->duration,
        'url' => $audio->url
    ]
]);
```

### 3. Game State Machine

Cada transición de estado se audita:

```php
// En GameStateMachine::transitionTo()
AuditService::log('game_state_change', [
    'actor_id' => $supervisor->id ?? null,
    'actor_type' => $supervisor ? 'supervisor' : 'system',
    'target_id' => $game->id,
    'target_type' => 'game',
    'context' => [
        'from_state' => $fromState,
        'to_state' => $toState,
        'game_type' => $game->type,
        'remaining_players' => $game->remaining_players_count
    ]
]);
```

## Supervisor Dashboard UI

### Componentes Frontend

#### `AuditLogsFeed.vue`

Feed en tiempo real de eventos críticos:

```vue
<template>
  <div class="audit-logs-feed">
    <div class="filters">
      <select v-model="filters.event_type">
        <option value="">All Events</option>
        <option value="player_action">Player Actions</option>
        <option value="supervisor_action">Supervisor Actions</option>
        <option value="game_state_change">Game State Changes</option>
      </select>
      
      <input type="date" v-model="filters.date_from" />
      <input type="date" v-model="filters.date_to" />
      
      <button @click="exportLogs">Export CSV</button>
    </div>
    
    <div class="logs-list">
      <div v-for="log in logs" :key="log.id" :class="['log-item', log.event_type]">
        <span class="timestamp">{{ formatDate(log.created_at) }}</span>
        <span class="event-type">{{ log.event_type }}</span>
        <span class="actor">{{ log.actor.name }}</span>
        <span class="action">{{ log.context.action }}</span>
        <button @click="showDetails(log)">Details</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// WebSocket listener
Echo.private('supervisor')
  .listen('AuditLogCreated', (data) => {
    logs.value.unshift(data);
    
    // Toast para eventos críticos
    if (data.event_type === 'supervisor_action') {
      toast.warning(`${data.actor.name}: ${data.context.action}`);
    }
  });
</script>
```

## Consideraciones de Seguridad

### 1. Acceso Restringido

Solo supervisors pueden consultar audit logs:

```php
// Middleware: EnsureSupervisorRole
Route::middleware(['auth', 'role:supervisor'])->group(function () {
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
});
```

### 2. PII Minimization

No almacenamos datos sensibles innecesarios:
- ❌ Passwords, tokens, API keys
- ❌ Números de tarjetas, emails completos
- ✅ IPs (necesario para security)
- ✅ User agents (necesario para debugging)

### 3. Encryption at Rest

S3 archivos encriptados:

```php
// En config/filesystems.php
's3' => [
    'driver' => 's3',
    'encryption' => 'AES256',
    // ...
],
```

### 4. Immutability

Logs nunca se editan, solo se crean:

```php
// AuditLog model
public static function boot()
{
    parent::boot();
    
    static::updating(function () {
        throw new \Exception('Audit logs are immutable');
    });
}
```

## Performance y Escalabilidad

### 1. Índices Compuestos

```sql
INDEX idx_composite_query (event_type, actor_type, created_at);
```

### 2. Particionamiento Mensual

Mejora queries por fecha y facilita purgas.

### 3. Queue para Broadcasts

Eventos no críticos se encolan:

```php
dispatch(function () use ($auditLog) {
    broadcast(new AuditLogCreated($auditLog));
})->afterResponse();
```

### 4. Compresión S3

Archivos JSON.gz reducen costos de storage:

```
audit_2025_12_21.json      →  1.2 MB
audit_2025_12_21.json.gz   →  150 KB (87.5% reducción)
```

## Testing

### Unit Tests

```php
// tests/Unit/AuditServiceTest.php

public function test_logs_player_action()
{
    $player = Player::factory()->create();
    
    $log = AuditService::log('player_action', [
        'actor_id' => $player->id,
        'actor_type' => 'player',
        'context' => ['action' => 'answer_question']
    ]);
    
    $this->assertDatabaseHas('audit_logs', [
        'id' => $log->id,
        'event_type' => 'player_action',
        'actor_id' => $player->id
    ]);
}

public function test_archives_old_logs_to_s3()
{
    Storage::fake('s3');
    
    // Crear logs antiguos
    AuditLog::factory()->count(10)->create([
        'created_at' => now()->subDays(35)
    ]);
    
    $archived = AuditService::archive(now()->subDays(30));
    
    $this->assertEquals(10, $archived);
    Storage::disk('s3')->assertExists('audits/2025/11/16.json.gz');
}
```

### Integration Tests

```php
// tests/Feature/AuditLogAPITest.php

public function test_supervisor_can_query_logs()
{
    $supervisor = User::factory()->supervisor()->create();
    
    $this->actingAs($supervisor)
        ->get('/api/supervisor/audit-logs?event_type=player_action')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
}

public function test_player_cannot_access_audit_logs()
{
    $player = Player::factory()->create();
    
    $this->actingAs($player)
        ->get('/api/supervisor/audit-logs')
        ->assertForbidden();
}
```

## Futuras Mejoras

1. **Machine Learning**: Detección de patrones anómalos
2. **Alertas Automáticas**: Notificar supervisor si se detecta fraude
3. **Visualizaciones**: Gráficos de actividad por hora/día
4. **Compliance Reports**: Exports formateados para auditorías externas
5. **Replay Mode**: Reproducir secuencia de eventos de un game

---

**Última actualización**: Diciembre 2025
