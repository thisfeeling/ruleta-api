# Bonus Games - Ruleta Familiar Backend

## Visión General

Los **bonus games** son juegos **no eliminatorios** que se activan manualmente por el supervisor entre juegos principales. Todos los jugadores participan, generan puntos en el scoreboard global, pero **NO eliminan** a nadie.

### Características Clave
- ✅ **Activación manual** por supervisor (control total del timing)
- ✅ **No eliminatorios** - solo agregan puntos al scoreboard
- ✅ **Estados**: `show` (visible pero no jugable) → `accesible` (jugable) → `completed`
- ✅ **Ganadores**: Primer completador (Word Search) o mejor tiempo (Flappy)
- ✅ **Validación server-side** con anti-cheat

---

## 1. ¡A Buscar! (Word Search)

### Concepto
Sopa de letras HTML/CSS puro (sin Phaser/Three.js). Grid 15×15 con **12 palabras** escondidas. Jugadores buscan todas las palabras. **El primero en completar las 12 palabras gana**.

### Arquitectura Backend

#### Base de Datos

**Tabla: `bonus_games`**
```sql
CREATE TABLE bonus_games (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  show_id BIGINT NOT NULL,
  game_type ENUM('word_search', 'flappy'),
  status ENUM('show', 'accesible', 'completed') DEFAULT 'show',
  game_data JSON,  -- Grid generado, palabras, config
  winner_id BIGINT NULL,
  started_at TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  FOREIGN KEY (show_id) REFERENCES shows(id),
  FOREIGN KEY (winner_id) REFERENCES players(id)
);
```

**Tabla: `word_search_progress`**
```sql
CREATE TABLE word_search_progress (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  bonus_game_id BIGINT NOT NULL,
  player_id BIGINT NOT NULL,
  found_words JSON DEFAULT '[]',  -- Array de palabras encontradas
  words_found_count INT DEFAULT 0,
  time_elapsed DECIMAL(8,2) DEFAULT 0,  -- Segundos
  completed BOOLEAN DEFAULT FALSE,
  completed_at TIMESTAMP NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  UNIQUE (bonus_game_id, player_id),
  FOREIGN KEY (bonus_game_id) REFERENCES bonus_games(id),
  FOREIGN KEY (player_id) REFERENCES players(id)
);
```

#### Generación del Grid

**Service: `WordSearchGenerator`**

```php
class WordSearchGenerator {
  private const GRID_SIZE = 15;
  private const WORD_COUNT = 12;
  private const DIRECTIONS = [
    'horizontal' => [0, 1],
    'vertical' => [1, 0],
    'diagonal_down' => [1, 1],
    'diagonal_up' => [-1, 1],
  ];
  
  public function generate(): array
  {
    $grid = array_fill(0, self::GRID_SIZE, array_fill(0, self::GRID_SIZE, ''));
    $words = $this->selectRandomWords(self::WORD_COUNT);
    $placements = [];
    
    foreach ($words as $word) {
      $placed = false;
      $attempts = 0;
      
      while (!$placed && $attempts < 100) {
        $direction = array_rand(self::DIRECTIONS);
        [$dx, $dy] = self::DIRECTIONS[$direction];
        
        $startRow = rand(0, self::GRID_SIZE - 1);
        $startCol = rand(0, self::GRID_SIZE - 1);
        
        if ($this->canPlaceWord($grid, $word, $startRow, $startCol, $dx, $dy)) {
          $this->placeWord($grid, $word, $startRow, $startCol, $dx, $dy);
          $placements[] = [
            'word' => $word,
            'start_row' => $startRow,
            'start_col' => $startCol,
            'direction' => $direction,
          ];
          $placed = true;
        }
        
        $attempts++;
      }
    }
    
    // Llenar espacios vacíos con letras random
    for ($i = 0; $i < self::GRID_SIZE; $i++) {
      for ($j = 0; $j < self::GRID_SIZE; $j++) {
        if ($grid[$i][$j] === '') {
          $grid[$i][$j] = chr(rand(65, 90)); // A-Z
        }
      }
    }
    
    return [
      'grid' => $grid,
      'words' => array_column($placements, 'word'),
      'placements' => $placements, // Solo para validación server-side
      'size' => self::GRID_SIZE,
    ];
  }
  
  private function canPlaceWord(array $grid, string $word, int $row, int $col, int $dx, int $dy): bool
  {
    $len = strlen($word);
    
    for ($i = 0; $i < $len; $i++) {
      $newRow = $row + ($i * $dx);
      $newCol = $col + ($i * $dy);
      
      if ($newRow < 0 || $newRow >= self::GRID_SIZE || 
          $newCol < 0 || $newCol >= self::GRID_SIZE) {
        return false;
      }
      
      if ($grid[$newRow][$newCol] !== '' && 
          $grid[$newRow][$newCol] !== $word[$i]) {
        return false;
      }
    }
    
    return true;
  }
  
  private function placeWord(array &$grid, string $word, int $row, int $col, int $dx, int $dy): void
  {
    $len = strlen($word);
    
    for ($i = 0; $i < $len; $i++) {
      $grid[$row + ($i * $dx)][$col + ($i * $dy)] = $word[$i];
    }
  }
  
  public function validateWord(array $placements, array $wordData): bool
  {
    foreach ($placements as $placement) {
      if ($placement['word'] === $wordData['word'] &&
          $placement['start_row'] === $wordData['start_row'] &&
          $placement['start_col'] === $wordData['start_col'] &&
          $placement['direction'] === $wordData['direction']) {
        return true;
      }
    }
    
    return false;
  }
  
  public function storeGrid(int $bonusGameId, array $gridData): void
  {
    // Guardar en Redis con TTL de 1 hora
    Redis::setex("word_search:{$bonusGameId}", 3600, json_encode($gridData));
  }
  
  private function selectRandomWords(int $count): array
  {
    $pool = [
      'FAMILIA', 'JUEGO', 'DIVERSION', 'GANADOR', 'RULETA',
      'TIEMPO', 'VICTORIA', 'DESAFIO', 'COMPETENCIA', 'PREMIO',
      'CAMPEON', 'PUNTOS', 'ELIMINADO', 'SUPERVIVIENTE', 'RONDA',
      'EQUIPO', 'ESTRATEGIA', 'HABILIDAD', 'TALENTO', 'SUERTE',
    ];
    
    shuffle($pool);
    return array_slice($pool, 0, $count);
  }
}
```

**Pool de palabras (español):**
```php
// config/word-search.php
return [
  'word_pool' => [
    'FAMILIA', 'JUEGO', 'DIVERSION', 'GANADOR', 'RULETA',
    'TIEMPO', 'VICTORIA', 'DESAFIO', 'COMPETENCIA', 'PREMIO',
    'CAMPEON', 'PUNTOS', 'ELIMINADO', 'SUPERVIVIENTE', 'RONDA',
    'EQUIPO', 'ESTRATEGIA', 'HABILIDAD', 'TALENTO', 'SUERTE',
    'TENSION', 'EMOCION', 'ADRENALINA', 'CONCENTRACION', 'RAPIDEZ',
  ],
  
  'grid_size' => 15,
  'word_count' => 12,
  'min_word_length' => 5,
  'max_word_length' => 14,
];
```

#### Flujo del Juego

```
1. Supervisor activa bonus game
   POST /api/supervisor/activate-bonus
   Body: { show_id, game_type: 'word_search' }
   
2. Laravel genera grid y guarda en Redis
   WordSearchGenerator::generate()
   
3. Laravel emite GridGenerated
   Event: WordSearch.GridGenerated
   
4. Bonus game queda en estado "show"
   (jugadores lo ven pero no pueden jugar)
   
5. Supervisor hace game "accesible"
   POST /api/supervisor/make-accessible/{bonusGameId}
   
6. Jugadores reciben grid (sin posiciones de palabras)
   GET /api/word-search/grid?bonus_game_id=123
   
7. Jugador encuentra palabra y envía
   POST /api/word-search/validate-word
   Body: {
     bonus_game_id,
     player_id,
     word: 'FAMILIA',
     start_row: 3,
     start_col: 5,
     direction: 'horizontal',
     time_elapsed: 45.2
   }
   
8. Laravel valida:
   - Grid existe en Redis
   - Coordenadas son válidas
   - Dirección es correcta
   - Palabra está en la lista oficial
   - Jugador no había encontrado esa palabra antes
   
9. Si válida:
   - Actualizar word_search_progress
   - Emitir WordFound event
   - Si completó 12 palabras Y es el primero:
     * Marcar como winner
     * Emitir GameCompleted event
     * Agregar puntos al scoreboard
```

#### Anti-Cheat

- **Grid server-side**: Cliente nunca recibe posiciones de palabras
- **Validación completa**: Backend valida coordenadas, dirección y secuencia
- **Redis con TTL**: Grid temporal, no manipulable
- **Tiempo mínimo**: Validar que `time_elapsed` sea razonable (min 2s por palabra)
- **Unicidad**: Cada jugador solo puede encontrar cada palabra una vez

#### Endpoints API

```php
// Supervisor - Activar bonus game
POST /api/supervisor/activate-bonus
Body: { show_id: number, game_type: 'word_search' }
Response: { bonus_game: BonusGame }

// Supervisor - Hacer accesible
POST /api/supervisor/make-accessible/{bonusGameId}
Response: { bonus_game: BonusGame }

// Player - Obtener grid
GET /api/word-search/grid?bonus_game_id=123
Response: { grid: string[][], size: 15, word_count: 12 }

// Player - Validar palabra
POST /api/word-search/validate-word
Body: {
  bonus_game_id: number,
  player_id: number,
  word: string,
  start_row: number,
  start_col: number,
  direction: 'horizontal' | 'vertical' | 'diagonal_down' | 'diagonal_up',
  time_elapsed: number
}
Response: {
  valid: boolean,
  words_found: number,
  remaining: number,
  completed?: boolean,
  is_winner?: boolean
}
```

#### Eventos WebSocket

```typescript
// Canal: show.{showId}

WordSearch.GridGenerated {
  bonus_game_id: number
  word_count: 12
  grid_size: 15
  status: 'show'
}

WordSearch.WordFound {
  player_id: number
  player_nickname: string
  player_number: number
  word: string
  words_found: number
  time_elapsed: number
}

WordSearch.GameCompleted {
  winner_id: number
  winner_nickname: string
  winner_number: number
  completion_time: number
  audio_url: string  // Narración victoria
}
```

---

## 2. No Lo Choques (Flappy Bird)

### Concepto
Juego tipo Flappy Bird con Phaser 3. **No hay límite de tiempo** - el jugador juega hasta morir. **El que más tiempo sobreviva gana** en el scoreboard.

### Arquitectura Backend

#### Base de Datos

**Tabla: `flappy_scores`**
```sql
CREATE TABLE flappy_scores (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  bonus_game_id BIGINT NOT NULL,
  player_id BIGINT NOT NULL,
  survival_time DECIMAL(8,2) NOT NULL,  -- Segundos sobrevividos
  pipes_passed INT DEFAULT 0,
  died_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  UNIQUE (bonus_game_id, player_id),  -- Un intento por jugador
  FOREIGN KEY (bonus_game_id) REFERENCES bonus_games(id),
  FOREIGN KEY (player_id) REFERENCES players(id)
);
```

#### Validación Anti-Cheat

**Service: `FlappyValidator`**

```php
class FlappyValidator {
  private const MAX_REASONABLE_SURVIVAL = 600;  // 10 min máximo
  private const MAX_PIPES_PER_SECOND = 2;
  private const MIN_SURVIVAL_TIME = 0.5; // 500ms mínimo
  
  public function validate(float $survivalTime, int $pipesPassed): array
  {
    $errors = [];
    
    // Validar tiempo razonable
    if ($survivalTime < self::MIN_SURVIVAL_TIME) {
      $errors[] = 'Tiempo de supervivencia muy bajo (posible manipulación)';
    }
    
    if ($survivalTime > self::MAX_REASONABLE_SURVIVAL) {
      $errors[] = 'Tiempo de supervivencia excede máximo razonable';
    }
    
    // Validar pipes vs tiempo
    $maxPossiblePipes = ceil($survivalTime * self::MAX_PIPES_PER_SECOND);
    if ($pipesPassed > $maxPossiblePipes) {
      $errors[] = 'Pipes pasados exceden máximo posible para el tiempo';
    }
    
    // Validar ratio mínimo (no puede pasar 0 pipes en 60s)
    if ($survivalTime > 10 && $pipesPassed < 1) {
      $errors[] = 'Ratio pipes/tiempo inconsistente';
    }
    
    return [
      'valid' => empty($errors),
      'errors' => $errors,
    ];
  }
  
  public function normalizeScore(float $survivalTime): int
  {
    // Normalización 0-1000 basada en tiempo de supervivencia
    // Máximo razonable: 600s = 1000 puntos
    $normalized = ($survivalTime / self::MAX_REASONABLE_SURVIVAL) * 1000;
    return min(1000, round($normalized));
  }
}
```

**Controller: `FlappyController`**

```php
class FlappyController extends Controller
{
  public function submitScore(Request $request)
  {
    $validated = $request->validate([
      'bonus_game_id' => 'required|exists:bonus_games,id',
      'player_id' => 'required|exists:players,id',
      'survival_time' => 'required|numeric|min:0.5|max:600',
      'pipes_passed' => 'required|integer|min:0',
    ]);
    
    $bonusGame = BonusGame::findOrFail($validated['bonus_game_id']);
    
    if ($bonusGame->status !== 'accesible') {
      return response()->json(['error' => 'Game not accessible'], 403);
    }
    
    // Validar anti-cheat
    $validator = new FlappyValidator();
    $validation = $validator->validate(
      $validated['survival_time'],
      $validated['pipes_passed']
    );
    
    if (!$validation['valid']) {
      Log::warning('Flappy anti-cheat violation', [
        'player_id' => $validated['player_id'],
        'errors' => $validation['errors'],
        'data' => $validated,
      ]);
      
      return response()->json([
        'error' => 'Score validation failed',
        'details' => $validation['errors'],
      ], 422);
    }
    
    // Verificar que el jugador no haya enviado score antes
    $existingScore = FlappyScore::where('bonus_game_id', $bonusGame->id)
      ->where('player_id', $validated['player_id'])
      ->first();
      
    if ($existingScore) {
      return response()->json(['error' => 'Score already submitted'], 409);
    }
    
    // Guardar score
    $score = FlappyScore::create([
      'bonus_game_id' => $bonusGame->id,
      'player_id' => $validated['player_id'],
      'survival_time' => $validated['survival_time'],
      'pipes_passed' => $validated['pipes_passed'],
      'died_at' => now(),
    ]);
    
    // Normalizar y agregar al scoreboard
    $normalizedScore = $validator->normalizeScore($validated['survival_time']);
    
    PlayerScore::create([
      'player_id' => $validated['player_id'],
      'show_id' => $bonusGame->show_id,
      'game_type' => 'flappy',
      'raw_score' => $validated['survival_time'],
      'normalized_score' => $normalizedScore,
      'metadata' => [
        'pipes_passed' => $validated['pipes_passed'],
        'bonus_game_id' => $bonusGame->id,
      ],
    ]);
    
    // Broadcast evento
    broadcast(new FlappyScoreSubmitted(
      $bonusGame->show_id,
      $validated['player_id'],
      $validated['survival_time'],
      $validated['pipes_passed']
    ));
    
    return response()->json([
      'message' => 'Score submitted successfully',
      'score' => $score,
      'normalized_score' => $normalizedScore,
    ]);
  }
}
```

#### Flujo del Juego

```
1. Supervisor activa bonus game
   POST /api/supervisor/activate-bonus
   Body: { show_id, game_type: 'flappy' }
   
2. Bonus game queda en estado "show"
   (jugadores lo ven pero no pueden jugar)
   
3. Supervisor hace game "accesible"
   POST /api/supervisor/make-accessible/{bonusGameId}
   Laravel emite Flappy.GameStarted
   
4. Cada jugador juega hasta morir (sin límite de tiempo)
   
5. Al morir, jugador envía score
   POST /api/flappy/submit-score
   Body: {
     bonus_game_id,
     player_id,
     survival_time: 127.5,  // Segundos
     pipes_passed: 45
   }
   
6. Laravel valida:
   - Tiempo no excede máximo razonable (600s)
   - Pipes consistentes con tiempo
   - Jugador no había enviado score antes
   
7. Si válido:
   - Guardar en flappy_scores
   - Emitir ScoreSubmitted event
   - Agregar puntos al scoreboard
   
8. Supervisor finaliza juego manualmente
   POST /api/flappy/end-game
   Body: { bonus_game_id }
   - Determinar ganador (mayor survival_time)
   - Emitir Flappy.GameEnded event
```

#### Endpoints API

```php
// Player - Submit score al morir
POST /api/flappy/submit-score
Body: {
  bonus_game_id: number,
  player_id: number,
  survival_time: number,  // Segundos
  pipes_passed: number
}
Response: {
  message: 'Score submitted',
  score: FlappyScore,
  normalized_score: number
}

// Supervisor - Finalizar juego
POST /api/flappy/end-game
Body: { bonus_game_id: number }
Response: {
  winner: Player,
  winning_time: number
}

// Public - Leaderboard actual
GET /api/flappy/leaderboard?bonus_game_id=123
Response: {
  leaderboard: [
    {
      player_id, nickname, number, color,
      survival_time, pipes_passed
    },
    ...
  ]
}
```

#### Eventos WebSocket

```typescript
// Canal: show.{showId}

Flappy.GameStarted {
  bonus_game_id: number
  status: 'accesible'
  message: '¡Comienza No Lo Choques! Sobrevive el mayor tiempo posible'
}

Flappy.ScoreSubmitted {
  player_id: number
  player_nickname: string
  player_number: number
  survival_time: number
  pipes_passed: number
}

Flappy.GameEnded {
  winner_id: number
  winner_nickname: string
  winner_number: number
  winning_time: number
  message: string
}
```

---

## Estados de Bonus Games

### Flujo de Estados

```
show (visible, no jugable)
  ↓ (supervisor activa)
accesible (jugable)
  ↓ (juego termina)
completed (finalizado)
```

### Transiciones

**Estado `show`:**
- Bonus game creado y visible para jugadores
- Frontend muestra preview/instrucciones
- **NO** se puede jugar aún
- Supervisor decide cuándo hacer `accesible`

**Estado `accesible`:**
- Jugadores pueden jugar
- Envío de acciones/scores habilitado
- Word Search: validación de palabras activa
- Flappy: submit de scores activo

**Estado `completed`:**
- Juego finalizado
- Winner declarado
- Puntos agregados al scoreboard
- No se aceptan más acciones

---

## Integración con State Machine

Los bonus games **NO están en el flujo principal** de `ShowPhase`. Son **paralelos** y activados manualmente:

```php
enum ShowPhase: string {
    case LOBBY = 'lobby';
    case INTRO = 'intro';
    case MILLIONAIRE = 'millionaire';
    case SPELL = 'spell';
    case ROPE = 'rope';
    case ROULETTE = 'roulette';
    case BONUS_WORD_SEARCH = 'bonus_word_search';  // Opcional
    case BONUS_FLAPPY = 'bonus_flappy';            // Opcional
    case WINNER = 'winner';
}
```

**Uso:**
- Show puede estar en `MILLIONAIRE` y `BONUS_WORD_SEARCH` simultáneamente
- Supervisor pausa juego principal → activa bonus → reanuda juego principal
- Bonus games son **independientes** del flujo eliminatorio

---

## Integración con Scoreboard

Ambos bonus games agregan puntos al **scoreboard global unificado**:

```php
PlayerScore::create([
  'player_id' => $player->id,
  'show_id' => $show->id,
  'game_type' => 'word_search',  // o 'flappy'
  'raw_score' => $rawScore,
  'normalized_score' => $normalized,  // 0-1000
  'metadata' => [...]
]);
```

Ver: `scoreboard-system.md` para detalles de normalización.

---

## Anti-Cheat Resumen

### Word Search
✅ Grid en Redis (server-side)  
✅ Validación completa de coordenadas/dirección  
✅ Tiempo mínimo por palabra  
✅ Unicidad de palabras por jugador  
✅ Lista oficial de palabras validada  

### Flappy Bird
✅ Tiempo máximo razonable (600s)  
✅ Pipes consistentes con tiempo  
✅ Un intento por jugador  
✅ Backend guarda timestamp de muerte  
✅ No se confía en score del cliente (solo tiempo)  

---

## Testing

### Unit Tests

```php
// WordSearchGeneratorTest
test('generates 15x15 grid')
test('places exactly 12 words')
test('validates word coordinates correctly')
test('rejects invalid directions')

// FlappyValidatorTest
test('accepts reasonable survival times')
test('rejects excessive survival times')
test('validates pipes vs time ratio')
```

### Feature Tests

```php
// WordSearchControllerTest
test('supervisor can activate word search')
test('players can validate words when accessible')
test('first completer wins')
test('cannot validate word twice')

// FlappyControllerTest
test('players can submit scores')
test('cannot submit score twice')
test('supervisor can end game')
test('winner is player with highest survival time')
```

---

**Ver también:**
- `scoreboard-system.md` - Puntuación unificada
- `state-machine.md` - Integración con flujo principal
- `api-contracts.md` - Contratos completos de API

---

**Última actualización**: Diciembre 2025
