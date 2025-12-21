# State Machine - Ruleta Familiar

## Fases del Show

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

**Nuevos estados bonus:**
- `BONUS_WORD_SEARCH`: Juego de sopa de letras (no eliminatorio)
- `BONUS_FLAPPY`: Juego tipo Flappy Bird (no eliminatorio)

### Clasificación de Fases

```php
// En ShowPhase enum
public function isBonus(): bool {
  return in_array($this, [
    self::BONUS_WORD_SEARCH,
    self::BONUS_FLAPPY,
  ]);
}

public function isEliminationGame(): bool {
  return in_array($this, [
    self::MILLIONAIRE,
    self::SPELL,
    self::ROPE,
    self::ROULETTE,
  ]);
}
```

## Flujo de Transiciones

### Flujo Principal (Sin Bonus)
```
LOBBY
  ↓ (supervisor inicia)
INTRO
  ↓ (auto tras narración)
MILLIONAIRE (ronda 1)
  ↓ (auto tras eliminaciones)
SPELL (ronda 1)
  ↓ (auto tras eliminaciones)
ROPE
  ↓ (auto tras eliminaciones)
MILLIONAIRE (ronda 2)
  ↓ (auto tras eliminaciones)
SPELL (ronda 2)
  ↓ (auto tras eliminaciones)
ROULETTE
  ↓ (un ganador)
WINNER
  ↓ (fin)
```

### Flujo con Bonus Games (Opcional)

Los bonus games se **insertan manualmente** por el supervisor entre cualquier fase:

```
MILLIONAIRE → SUPERVISOR ACTIVA BONUS → BONUS_WORD_SEARCH → Vuelve a fase anterior o salta a siguiente

Ejemplo timeline:
1. MILLIONAIRE (ronda 1) → eliminaciones
2. SUPERVISOR PAUSA
3. BONUS_WORD_SEARCH activado
4. Jugadores juegan (no eliminatorio)
5. SUPERVISOR RESUME
6. SPELL (ronda 1) continúa
```

**Importante:**
- Los bonus NO están en el flujo automático
- NO tienen `getNext()` definido
- Se activan solo por supervisor vía API
- El show puede estar en una fase principal Y tener bonus activo simultáneamente

## Implementación

```php
class ShowMachine {
  public function transition(ShowPhase $to): void
  {
    if (!$this->canTransition($to)) {
      throw new InvalidTransitionException();
    }
    
    $this->show->phase = $to;
    $this->show->save();
    
    event(new ShowStateChanged($this->show, $to));
  }
  
  public function canTransition(ShowPhase $to): bool
  {
    return match($this->show->phase) {
      ShowPhase::LOBBY => $to === ShowPhase::INTRO,
      ShowPhase::INTRO => $to === ShowPhase::MILLIONAIRE,
      ShowPhase::MILLIONAIRE => $to === ShowPhase::SPELL,
      ShowPhase::SPELL => $to === ShowPhase::ROPE,
      ShowPhase::ROPE => $to === ShowPhase::MILLIONAIRE, // Ronda 2
      ShowPhase::ROULETTE => $to === ShowPhase::WINNER,
      
      // Bonus games no tienen transiciones automáticas
      ShowPhase::BONUS_WORD_SEARCH => false,
      ShowPhase::BONUS_FLAPPY => false,
      
      ShowPhase::WINNER => false,
      default => false,
    };
  }
  
  public function next(): void
  {
    // Solo avanza fases principales
    if ($this->show->phase->isBonus()) {
      throw new InvalidTransitionException('Bonus games require manual phase change');
    }
    
    $next = $this->getNextPhase();
    $this->transition($next);
  }
}
```

### Gestión de Bonus Games

Los bonus games se manejan en **paralelo** al flujo principal:

```php
// Tabla bonus_games tiene su propio estado
BonusGame {
  status: 'show' | 'accesible' | 'completed'
}

// El ShowPhase puede ser MILLIONAIRE
// Mientras hay un BonusGame activo en background
```

**Patrón recomendado:**

1. Show avanza normalmente por fases principales
2. En cualquier momento, supervisor activa bonus:
   ```php
   POST /api/supervisor/activate-bonus { game_type: 'word_search' }
   // Crea BonusGame con status: 'show'
   ```
3. Supervisor hace bonus accesible cuando quiera:
   ```php
   POST /api/supervisor/make-accessible/{bonusGameId}
   // BonusGame.status = 'accesible'
   ```
4. Jugadores juegan bonus (no afecta show.phase)
5. Bonus termina (completed)
6. Supervisor resume flujo principal normalmente

## Reglas de Transición

- Solo supervisores pueden iniciar desde LOBBY
- Resto de transiciones automáticas (excepto bonus)
- No se puede retroceder
- WINNER es estado final
- **Bonus games son independientes** del flujo principal
- Bonus games NO tienen transiciones automáticas
- Supervisor controla cuándo activar/pausar bonus

**Ver también**: `architecture.md`, `game-implementation.md`, `bonus-games.md`
