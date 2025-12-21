# Game Implementation - Ruleta Familiar

## 1. Juego del Millonario

### Flujo
```
1. Laravel emite QuestionDisplayed
2. Jugadores envían respuesta
3. Laravel valida y calcula puntos
4. Laravel emite AnswerValidated por jugador
5. Tras N preguntas, Laravel elimina X jugadores con menor puntaje
6. Laravel emite PlayerEliminated por cada uno
7. Laravel transiciona a siguiente juego
```

### Endpoints
```php
POST /api/millionaire/answer
Body: { answer: 'A' | 'B' | 'C' | 'D' }
```

### Lógica de Puntos
```php
if ($correct && $timeUsed < 5) {
  $points = 3;
} elseif ($correct) {
  $points = 1;
} else {
  $points = -2;
  $player->cooldown_until = now()->addSeconds(15);
}
```

### Eliminación
```php
$losers = Player::where('status', 'alive')
  ->orderBy('points')
  ->limit($eliminateCount)
  ->get();

foreach ($losers as $loser) {
  app(EliminatePlayer::class)->handle($loser);
}
```

---

## 2. La Cuerda

### Flujo
```
1. Laravel forma grupos (5 jugadores por grupo)
2. Laravel emite GameStarted con asignación de grupos
3. Jugadores clickean
4. Laravel calcula tensión por grupo en tiempo real
5. Laravel emite RopeStateUpdated cada 100ms
6. Si tensión > 1 → grupo pierde
7. Laravel elimina TODOS del grupo perdedor
8. Laravel transiciona a siguiente juego
```

### Endpoints
```php
POST /api/rope/click
Body: {}
```

### Formación de Grupos
```php
$groupSize = 5;
$players = Player::where('status', 'alive')->get();
$groups = $players->chunk($groupSize);

// Si último grupo < 3, redistribuir
```

### Cálculo de Tensión
```php
$groupATension = ($groupAClicks - $groupBClicks) / 100;
// Valor entre -1 y 1
```

### Three.js (Frontend)
```javascript
// Vue solo recibe tensión y anima
ropeVisual.updateTension(e.tension)
```

---

## 3. Deletréalo

### Flujo
```
1. Laravel elige jugador aleatorio
2. Laravel elige palabra del diccionario
3. Laravel emite PlayerSelected { player_id, word, time_limit: 15 }
4. Jugador graba audio
5. Jugador envía audio
6. Laravel guarda en S3 + BD (status: pending)
7. Laravel notifica supervisores
8. Supervisor escucha y valida
9. Laravel emite SpellValidated
10. Si rejected → EliminatePlayer
11. Siguiente jugador
```

### Endpoints
```php
POST /api/spell/submit
Body: FormData { audio: File, word: string }

POST /api/spell/validate (supervisor only)
Body: { submission_id: number, result: 'approved' | 'rejected' }
```

### Validación de Audio
```php
$request->validate([
  'audio' => 'required|file|mimes:webm,mp3,ogg|max:5120', // 5MB
]);

$path = $request->file('audio')->store('spell', 'rustfs');
```

### Diccionario
```php
$words = [
  'murciélago', 'excelente', 'horizonte', 
  'responsabilidad', 'extraordinario'
];

$word = $words[array_rand($words)];
```

---

## 4. La Ruleta Final

### Flujo
```
1. Laravel emite GameStarted { survivors: [...] }
2. Laravel elige turno aleatorio
3. Jugador actual gira ruleta
4. Laravel calcula sector aleatorio
5. Laravel aplica efecto (suma, x2, pierde todo)
6. Laravel emite SpinResult { player_id, sector, points, total }
7. Si total >= 5000 → GANADOR
8. Laravel elimina a TODOS los demás
9. Laravel transiciona a WINNER
```

### Endpoints
```php
POST /api/roulette/spin
Body: {}
```

### Sectores
```php
$sectors = [
  ['value' => 50, 'weight' => 30],
  ['value' => 100, 'weight' => 25],
  ['value' => 200, 'weight' => 20],
  ['value' => 500, 'weight' => 10],
  ['value' => 1000, 'weight' => 5],
  ['value' => 'LOSE_ALL', 'weight' => 5],
  ['value' => 'X2', 'weight' => 5],
];

$sector = $this->weightedRandom($sectors);
```

### Aplicar Efecto
```php
match($sector) {
  50, 100, 200, 500, 1000 => $player->points += $sector,
  'LOSE_ALL' => $player->points = 0,
  'X2' => $player->points *= 2,
};
```

### Victoria
```php
if ($player->points >= 5000) {
  $this->show->winner_id = $player->id;
  $this->show->save();
  
  // Eliminar a todos los demás
  Player::where('id', '!=', $player->id)
    ->where('status', 'alive')
    ->each(fn($p) => app(EliminatePlayer::class)->handle($p));
  
  event(new ShowEnded($player));
}
```

---

## 5. ¡A Buscar! (Word Search) - BONUS

**Tipo**: No eliminatorio, activado manualmente por supervisor

### Flujo
```
1. Supervisor activa bonus game
   POST /api/supervisor/activate-bonus { game_type: 'word_search' }
2. Laravel genera grid 15×15 con 12 palabras
3. Laravel guarda grid en Redis (TTL 1h)
4. Laravel emite GridGenerated (estado: show)
5. Supervisor hace game "accesible"
   POST /api/supervisor/make-accessible/{bonusGameId}
6. Jugadores solicitan grid
   GET /api/word-search/grid
7. Jugadores buscan palabras y envían validación
   POST /api/word-search/validate-word {
     word, start_row, start_col, direction, time_elapsed
   }
8. Laravel valida contra grid en Redis
9. Si correcto y no duplicado:
   - Actualizar word_search_progress
   - Emitir WordFound
10. Si jugador completa 12 palabras Y es el primero:
   - Marcar como winner
   - Agregar puntos al scoreboard
   - Emitir GameCompleted
```

### Endpoints
```php
POST /api/supervisor/activate-bonus
Body: { show_id, game_type: 'word_search' }

POST /api/supervisor/make-accessible/{bonusGameId}

GET /api/word-search/grid?bonus_game_id=123
Response: { grid: string[][], size: 15, word_count: 12 }

POST /api/word-search/validate-word
Body: {
  bonus_game_id,
  player_id,
  word,
  start_row,
  start_col,
  direction: 'horizontal' | 'vertical' | 'diagonal_down' | 'diagonal_up',
  time_elapsed
}
```

### Generación del Grid
```php
WordSearchGenerator::generate()
// 1. Grid vacío 15×15
// 2. Seleccionar 12 palabras random del pool
// 3. Colocar cada palabra en dirección aleatoria
// 4. Llenar espacios con letras random
// 5. Guardar en Redis
```

### Validación
```php
// Validar que palabra existe en grid en coordenadas dadas
WordSearchGenerator::validateWord($grid, $wordData)

// Anti-cheat:
- Grid server-side en Redis (cliente nunca ve posiciones)
- Validación completa de coordenadas y dirección
- Tiempo mínimo razonable (2s+ por palabra)
- Cada palabra solo se cuenta una vez por jugador
```

### Puntuación
Solo el **primer jugador en completar las 12 palabras** gana y recibe puntos:

```php
PlayerScore::create([
  'game_type' => 'word_search',
  'raw_score' => $timeElapsed,  // Segundos
  'normalized_score' => max(0, 1000 - (($timeElapsed / 300) * 1000))
]);

// Ejemplo: 45s → 850 points, 150s → 500 points
```

---

## 6. No Lo Choques (Flappy Bird) - BONUS

**Tipo**: No eliminatorio, activado manualmente por supervisor

### Flujo
```
1. Supervisor activa bonus game
   POST /api/supervisor/activate-bonus { game_type: 'flappy' }
2. Bonus game en estado "show"
3. Supervisor hace game "accesible"
4. Laravel emite Flappy.GameStarted
5. Cada jugador juega hasta morir (sin límite de tiempo)
6. Al morir, jugador envía score
   POST /api/flappy/submit-score {
     bonus_game_id, player_id, survival_time, pipes_passed
   }
7. Laravel valida:
   - Tiempo no excede 600s (razonable)
   - Pipes consistentes con tiempo
   - No había enviado score antes
8. Guardar en flappy_scores
9. Emitir ScoreSubmitted
10. Agregar puntos al scoreboard
11. Supervisor finaliza juego cuando quiera
    POST /api/flappy/end-game { bonus_game_id }
12. Determinar ganador (mayor survival_time)
13. Emitir Flappy.GameEnded
```

### Endpoints
```php
POST /api/flappy/submit-score
Body: {
  bonus_game_id,
  player_id,
  survival_time,  // Segundos sobrevividos
  pipes_passed    // Número de pipes
}

POST /api/flappy/end-game
Body: { bonus_game_id }

GET /api/flappy/leaderboard?bonus_game_id=123
```

### Validación Anti-Cheat
```php
FlappyValidator::validate($survivalTime, $pipesPassed)

// Reglas:
- survivalTime <= 600s (10 min máximo razonable)
- pipesPassed <= ceil(survivalTime * 2)  // Máx 2 pipes/segundo
- Un intento por jugador (unique constraint)
```

### Puntuación
**Todos** los jugadores que juegan reciben puntos (no solo el ganador):

```php
PlayerScore::create([
  'game_type' => 'flappy',
  'raw_score' => $survivalTime,
  'normalized_score' => min(1000, round(($survivalTime / 100) * 1000))
]);

// Ejemplo: 30s → 300 points, 75s → 750 points, 100s+ → 1000 points
```

El **ganador** es quien tiene el mayor `survival_time`.

---

## Reglas Generales

### Todas las acciones validan:
1. Jugador está vivo
2. Juego en fase correcta
3. Es turno del jugador (si aplica)
4. No está en cooldown

### Todos los juegos emiten:
1. GameStarted al inicio
2. GameEnded al finalizar
3. Eventos de estado durante el juego

### Audio se reproduce:
1. Al iniciar juego
2. Al eliminar jugador
3. Al pasar jugador
4. En eventos críticos

**Ver también**: `api-contracts.md`, `state-machine.md`, `audio-system.md`
