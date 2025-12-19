# API Contracts - Ruleta Familiar

## HTTP Endpoints

### Auth
```
POST /api/auth/register
POST /api/auth/login
POST /api/auth/logout
```

### Player
```
GET  /api/player/{id}
POST /api/player/join          # Unirse al lobby
POST /api/player/reconnect     # Reconectar con PIN
```

### Games
```
POST /api/millionaire/answer
POST /api/rope/click
POST /api/spell/submit
POST /api/spell/validate      # Solo supervisores
POST /api/roulette/spin
```

### Chat
```
POST /api/chat/send
GET  /api/chat/messages
```

---

## WebSocket Events (Server → Client)

### Player Events
```typescript
PlayerJoined {
  player: {
    id: number
    nickname: string
    number: number
    color: string
  }
}

PlayerEliminated {
  player_id: number
  reason: string
}
```

### Show Events
```typescript
ShowStateChanged {
  phase: 'lobby' | 'millionaire' | 'spell' | 'rope' | 'roulette' | 'winner'
  players_alive: number
}

AudioRequested {
  url: string
  context: string
}
```

### Game Events
```typescript
// Millonario
QuestionDisplayed {
  question: string
  options: string[]
  time_limit: number
}

AnswerValidated {
  player_id: number
  correct: boolean
  points: number
}

// Cuerda
RopeStateUpdated {
  group_id: number
  tension: number  // -1 a 1
}

// Deletréalo
SpellValidated {
  player_id: number
  result: 'approved' | 'rejected'
}

// Ruleta
SpinResult {
  player_id: number
  sector: string
  points: number
  total: number
}
```

---

## WebSocket Events (Client → Server)

```typescript
// Acciones de juego
{
  type: 'answer',
  data: { answer: 'A' | 'B' | 'C' | 'D' }
}

{
  type: 'click'
}

{
  type: 'spin'
}

// Chat
{
  type: 'chat',
  data: { message: string }
}
```

---

## Canales WebSocket

```
public:
  show.{id}           - Estado general
  chat                - Chat global

private:
  player.{id}         - Notificaciones personales
  game.{type}         - Estado del juego actual

presence:
  supervisor.show     - Supervisores online
```

---

## Response Format

### Success
```json
{
  "success": true,
  "data": { ... },
  "message": "Action completed"
}
```

### Error
```json
{
  "success": false,
  "error": {
    "code": "PLAYER_ELIMINATED",
    "message": "No puedes jugar si estás eliminado"
  }
}
```

---

## Validación

Todas las requests validadas con Laravel Form Requests:

```php
class PlayerJoinRequest extends FormRequest {
  public function rules() {
    return [
      'nickname' => 'required|string|max:50',
      'color' => 'required|regex:/^#[0-9A-F]{6}$/i',
      'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
    ];
  }
}
```

**Ver también**: `security-guidelines.md`, `game-implementation.md`
