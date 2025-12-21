# Scoreboard System - Ruleta Familiar Backend

## Visión General

El **scoreboard** es un sistema de puntuación **unificado** que agrega puntos de **todos los juegos** (eliminatorios + bonus) en un ranking global. Cada juego tiene su propia métrica raw, pero todas se normalizan a un rango 0-1000 para comparación justa.

### Características Clave
- ✅ **Normalización 0-1000** por juego
- ✅ **Suma acumulativa** de todos los juegos
- ✅ **Tiempo real** vía WebSocket
- ✅ **Histórico persistente** en base de datos
- ✅ **No eliminatorio** - solo informativo

---

## Base de Datos

### Tabla: `player_scores`

```sql
CREATE TABLE player_scores (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  player_id BIGINT NOT NULL,
  show_id BIGINT NOT NULL,
  game_type ENUM('millionaire', 'rope', 'spell', 'roulette', 'word_search', 'flappy'),
  raw_score DECIMAL(10,2) DEFAULT 0,      -- Score original del juego
  normalized_score INT DEFAULT 0,         -- Score normalizado 0-1000
  metadata JSON NULL,                     -- Datos extra del juego
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  INDEX (show_id, game_type),
  INDEX (player_id, show_id),
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (show_id) REFERENCES shows(id) ON DELETE CASCADE
);
```

**Campos:**
- `game_type`: Tipo de juego que generó el score
- `raw_score`: Puntuación original (ej: 75% correct, 120s survival)
- `normalized_score`: Puntuación normalizada a 0-1000
- `metadata`: JSON con datos específicos del juego

---

## Normalizaciones por Juego

Cada juego tiene su propia fórmula de normalización para convertir su métrica raw a un score 0-1000:

### 1. Millonario (Preguntas)
**Métrica raw:** Porcentaje de respuestas correctas (0-100%)

```php
normalized = (correctPercentage / 100) * 1000

// Ejemplo:
// 75% correct → 750 points
// 90% correct → 900 points
```

**Metadata:**
```json
{
  "questions_answered": 10,
  "correct_answers": 7,
  "avg_response_time": 8.5
}
```

### 2. La Cuerda (Clicks grupales)
**Métrica raw:** Clicks individuales del jugador

```php
normalized = (playerClicks / groupTotalClicks) * 1000

// Ejemplo:
// Jugador: 50 clicks, Grupo total: 200 clicks
// 50/200 * 1000 = 250 points
```

**Metadata:**
```json
{
  "player_clicks": 50,
  "group_total_clicks": 200,
  "group_id": 2,
  "survived": true
}
```

### 3. Deletréalo (Deletreo)
**Métrica raw:** Número de palabras correctas

```php
normalized = min(1000, correctCount * 500)

// Ejemplo:
// 1 palabra correcta → 500 points
// 2 palabras correctas → 1000 points (máximo)
```

**Metadata:**
```json
{
  "words_attempted": 2,
  "words_correct": 1,
  "audio_approved": true
}
```

### 4. La Ruleta (Final)
**Métrica raw:** Puntos finales acumulados (objetivo: 5000)

```php
normalized = min(1000, round(finalPoints / 5))

// Ejemplo:
// 2500 points → 500 normalized
// 5000 points (ganador) → 1000 normalized
```

**Metadata:**
```json
{
  "final_points": 2500,
  "spins_count": 8,
  "lost_all_count": 1
}
```

### 5. Word Search (Bonus)
**Métrica raw:** Tiempo de completación (segundos)

```php
maxTime = 300  // 5 minutos máximo
normalized = max(0, round(1000 - ((timeElapsed / maxTime) * 1000)))

// Ejemplo:
// 45s → 850 points
// 150s → 500 points
// 300s → 0 points
```

**Solo el ganador (primer completador) recibe puntos.**

**Metadata:**
```json
{
  "completion_time": 45.2,
  "words_found": 12,
  "is_winner": true
}
```

### 6. Flappy Bird (Bonus)
**Métrica raw:** Tiempo de supervivencia (segundos)

```php
normalized = min(1000, round((survivalTime / 100) * 1000))

// Ejemplo:
// 30s → 300 points
// 75s → 750 points
// 100s+ → 1000 points (máximo)
```

**Metadata:**
```json
{
  "survival_time": 75.5,
  "pipes_passed": 25
}
```

---

## Ranking Global

### Cálculo

```php
// Sumar todos los normalized_score por jugador
SELECT 
  player_id,
  SUM(normalized_score) as total_score,
  COUNT(*) as games_played
FROM player_scores
WHERE show_id = ?
GROUP BY player_id
ORDER BY total_score DESC;
```

### Ejemplo de Rankings

| Rank | Player | Millionaire | Rope | Spell | Roulette | Word Search | Flappy | **Total** |
|------|--------|-------------|------|-------|----------|-------------|--------|-----------|
| 1    | Ana    | 900         | 600  | 500   | 800      | 850         | 750    | **4400**  |
| 2    | Luis   | 850         | 700  | 1000  | 600      | 0           | 900    | **4050**  |
| 3    | María  | 750         | 500  | 500   | 900      | 0           | 600    | **3250**  |

**Nota:** Word Search muestra 0 para quienes no ganaron (solo el primer completador recibe puntos).

---

## Eventos WebSocket

### 1. ScoreAdded
Se emite cada vez que un jugador recibe puntos en cualquier juego.

```typescript
// Canal: show.{showId}
Event: Scoreboard.ScoreAdded

Payload: {
  player_id: number
  player_nickname: string
  player_number: number
  game_type: 'millionaire' | 'rope' | 'spell' | 'roulette' | 'word_search' | 'flappy'
  raw_score: number
  normalized_score: number  // 0-1000
}
```

**Cuándo se emite:**
- Jugador completa ronda de Millonario
- Juego de La Cuerda termina
- Jugador aprueba palabra en Deletréalo
- Jugador gira en La Ruleta
- Jugador gana Word Search (primer completador)
- Jugador muere en Flappy Bird

### 2. ScoreboardUpdated
Se emite cuando el ranking global cambia (después de agregar scores).

```typescript
// Canal: show.{showId}
Event: Scoreboard.Updated

Payload: {
  rankings: [
    {
      player_id: number
      nickname: string
      number: number
      color: string
      total_score: number
    },
    ...
  ],
  updated_at: string  // ISO timestamp
}
```

**Cuándo se emite:**
- Después de cada `ScoreAdded`
- Supervisor solicita refresh manual
- Juego eliminator io termina

---

## Endpoints API

### Get Scoreboard
```php
GET /api/scoreboard?show_id=123

Response: {
  scoreboard: [
    {
      player_id: number,
      nickname: string,
      number: number,
      color: string,
      total_score: number,
      games_played: number,
      breakdown: {
        millionaire: number,
        rope: number,
        spell: number,
        roulette: number,
        word_search: number,
        flappy: number
      }
    },
    ...
  ]
}
```

### Get Player Score History
```php
GET /api/player/{playerId}/scores?show_id=123

Response: {
  player: Player,
  scores: [
    {
      game_type: string,
      raw_score: number,
      normalized_score: number,
      metadata: object,
      created_at: timestamp
    },
    ...
  ],
  total_score: number
}
```

---

## Lógica Backend

### Service: `ScoreboardService`

```php
class ScoreboardService {
  /**
   * Add score for a player
   */
  public function addScore(
    Player $player,
    Show $show,
    string $gameType,
    float $rawScore,
    ?array $metadata = []
  ): PlayerScore {
    $normalized = $this->normalize($gameType, $rawScore, $metadata);
    
    $score = PlayerScore::create([
      'player_id' => $player->id,
      'show_id' => $show->id,
      'game_type' => $gameType,
      'raw_score' => $rawScore,
      'normalized_score' => $normalized,
      'metadata' => $metadata,
    ]);
    
    // Broadcast
    event(new ScoreAdded($player, $score));
    $this->broadcastRankings($show->id);
    
    return $score;
  }
  
  /**
   * Get current rankings
   */
  public function getRankings(int $showId): array
  {
    return PlayerScore::where('show_id', $showId)
      ->selectRaw('player_id, SUM(normalized_score) as total_score')
      ->groupBy('player_id')
      ->orderByDesc('total_score')
      ->with('player')
      ->get()
      ->map(fn($item) => [
        'player_id' => $item->player_id,
        'nickname' => $item->player->nickname,
        'number' => $item->player->number,
        'color' => $item->player->color,
        'total_score' => $item->total_score,
      ])
      ->toArray();
  }
  
  /**
   * Normalize score based on game type
   */
  private function normalize(string $gameType, float $rawScore, array $metadata): int
  {
    return match($gameType) {
      'millionaire' => $this->normalizeMillionaire($rawScore),
      'rope' => $this->normalizeRope($rawScore, $metadata),
      'spell' => $this->normalizeSpell($rawScore),
      'roulette' => $this->normalizeRoulette($rawScore),
      'word_search' => $this->normalizeWordSearch($rawScore),
      'flappy' => $this->normalizeFlappy($rawScore),
      default => 0,
    };
  }
}
```

---

## Componentes Frontend (Referencia)

### ScoreboardCompact (HUD)
Versión compacta visible durante los juegos:
- Top 5 jugadores
- Score propio destacado
- Actualización en tiempo real

### Scoreboard (Completo)
Vista completa entre juegos o en dashboard supervisor:
- Todos los jugadores
- Desglose por juego
- Gráficos de progresión
- Filtros y ordenamiento

---

## Reglas Importantes

### ✅ Todos los juegos suman
- Eliminatorios: Millionaire, Rope, Spell, Roulette
- Bonus: Word Search, Flappy

### ✅ Normalización justa
- Cada juego normalizado a 0-1000
- Permite comparación equitativa
- Suma simple para total

### ✅ No afecta eliminaciones
- Scoreboard es **informativo**
- Eliminaciones siguen su propia lógica
- Puedes estar #1 en scoreboard y ser eliminado

### ✅ Persistencia
- Todos los scores guardados en BD
- Histórico auditable
- Rejugabilidad del show

### ❌ No hay puntos negativos
- Mínimo siempre es 0
- No se restan puntos por errores

---

## Testing

### Unit Tests

```php
// ScoreboardServiceTest
test('normalizes millionaire scores correctly')
test('normalizes rope scores with group context')
test('normalizes word search based on time')
test('normalizes flappy based on survival')
test('calculates rankings correctly')
test('handles multiple games per player')
```

### Feature Tests

```php
// ScoreboardControllerTest
test('returns current rankings')
test('returns player score history')
test('broadcasts score added event')
test('broadcasts scoreboard updated event')
```

---

## Optimizaciones

### Caché
```php
// Cachear rankings por 30 segundos
Cache::remember("rankings:{$showId}", 30, function() use ($showId) {
  return $this->scoreboardService->getRankings($showId);
});
```

### Índices
- `(show_id, game_type)` - Filtrar scores por juego
- `(player_id, show_id)` - Histórico del jugador

### Broadcasting
- Usar queue para eventos
- Throttle actualizaciones a máx 1/segundo

---

**Ver también:**
- `bonus-games.md` - Juegos que agregan scores
- `game-implementation.md` - Cuándo se agregan puntos
- `api-contracts.md` - Eventos WebSocket completos

---

**Última actualización**: Diciembre 2025
