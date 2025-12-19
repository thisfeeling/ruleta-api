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
    case WINNER = 'winner';
}
```

## Flujo de Transiciones

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
      // ... resto de transiciones
      default => false,
    };
  }
  
  public function next(): void
  {
    $next = $this->getNextPhase();
    $this->transition($next);
  }
}
```

## Reglas de Transición

- Solo supervisores pueden iniciar desde LOBBY
- Resto de transiciones automáticas
- No se puede retroceder
- WINNER es estado final

**Ver también**: `architecture.md`, `game-implementation.md`
