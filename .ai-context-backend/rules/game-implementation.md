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
