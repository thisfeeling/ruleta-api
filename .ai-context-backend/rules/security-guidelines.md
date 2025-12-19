# Security Guidelines - Ruleta Familiar

## Principios

1. **Servidor Autoritativo** - Laravel decide TODO
2. **Validación Estricta** - Toda entrada validada
3. **Rate Limiting** - Protección contra spam/abuse
4. **Auditoría** - Logs de acciones críticas

## Validación de Acciones

```php
// Antes de ejecutar CUALQUIER acción de juego
if ($player->status !== 'alive') {
    abort(403, 'No puedes jugar si estás eliminado');
}

if ($show->phase !== ShowPhase::MILLIONAIRE) {
    abort(400, 'Acción inválida para esta fase');
}

if ($player->inCooldown()) {
    abort(429, 'Estás en cooldown');
}
```

## Rate Limiting

### API
```php
Route::middleware('throttle:actions')->group(function () {
    Route::post('/millionaire/answer', ...);
    Route::post('/rope/click', ...);
});

// config/throttle.php
'actions' => [
    'limit' => 10,
    'every' => 1, // segundo
],
```

### WebSocket
```php
Broadcast::channel('game.{type}', ..., ['throttle' => 'ws:10,1']);
```

### Chat
```php
Route::post('/chat/send')->middleware('throttle:chat:1,60'); // 1 por minuto
```

## Autenticación

### HTTP (Sanctum)
```php
Route::middleware('auth:sanctum')->group(...);
```

### WebSocket
```php
// Laravel genera token
$token = $user->createToken('ws')->plainTextToken;

// Vue lo usa
Echo.connector.options.auth = {
  headers: { Authorization: `Bearer ${token}` }
};
```

## Autorización

```php
// Policies
Gate::define('validate-spell', function (User $user) {
    return $user->role === 'supervisor';
});

// Uso
if (!Gate::allows('validate-spell')) {
    abort(403);
}
```

## PIN de Reconexión

```php
// NUNCA exponer PIN en broadcast
// Solo en respuesta directa a jugador
return response()->json([
    'player' => $player->only(['id', 'nickname', 'number']),
    'code' => $player->code, // Solo aquí
]);

// En eventos públicos
event(new PlayerJoined($player->only(['id', 'nickname', 'number'])));
```

## Auditoría

```php
Log::channel('audit')->info('Player eliminated', [
    'player_id' => $player->id,
    'game' => 'millionaire',
    'eliminated_by' => 'system',
    'reason' => 'timeout',
]);
```

## CORS

```php
// config/cors.php
'paths' => ['api/*'],
'allowed_origins' => [env('FRONTEND_URL')],
'allowed_methods' => ['*'],
'supports_credentials' => true,
```

## Sanitización

```php
// Chat
$message = strip_tags($request->message);
$message = Str::limit($message, 200);

// Nickname
$nickname = filter_var($request->nickname, FILTER_SANITIZE_STRING);
```

## NO Exponer

❌ API keys en cliente
❌ Lógica de juego en frontend
❌ PINs de otros jugadores
❌ IDs de supervisores
❌ Claves de S3

**Ver también**: `architecture.md`, `api-contracts.md`
