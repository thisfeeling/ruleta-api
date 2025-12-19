# Reverb WebSockets - Ruleta Familiar

## Arquitectura de Reverb

Laravel Reverb es el servidor WebSocket nativo de Laravel que vive **dentro** del mismo contenedor de Laravel.

```
Laravel Container
├── PHP-FPM (HTTP)
└── Reverb (WebSocket) ← mismo proceso supervisado
```

## Inicio de Reverb

### Desarrollo
```bash
php artisan reverb:start
```

### Producción (Supervisor)
```conf
[program:reverb]
command=php artisan reverb:start --host=0.0.0.0 --port=8080
directory=/var/www/html
autostart=true
autorestart=true
user=www-data
```

## Canales

### Públicos
```php
// show.{id} - Estado general del show
Broadcast::channel('show.{id}', function ($user, $id) {
    return true; // Todos pueden ver
});

// chat - Chat global
Broadcast::channel('chat', function ($user) {
    return true;
});
```

### Privados
```php
// player.{id} - Notificaciones personales
Broadcast::channel('player.{id}', function ($user, $id) {
    return $user->id === (int) $id;
});

// game.{type} - Estado del juego actual
Broadcast::channel('game.{type}', function ($user, $type) {
    return $user->status === 'alive';
});
```

### Presence
```php
// supervisor.show - Supervisores online
Broadcast::channel('supervisor.show', function ($user) {
    return $user->role === 'supervisor' ? [
        'id' => $user->id,
        'nickname' => $user->nickname,
    ] : null;
});
```

## Eventos Principales

```php
// Player events
PlayerJoined
PlayerReconnected
PlayerEliminated
PlayerPassed

// Show events
ShowStateChanged
GameStarted
GameEnded
ScreenChanged

// Audio events
AudioRequested
NumberCalled

// Chat events
ChatMessageSent

// Game-specific
AnswerValidated (Millonario)
RopeStateUpdated (Cuerda)
SpellValidated (Deletréalo)
SpinResult (Ruleta)
```

## Cliente (Vue + laravel-echo)

```javascript
import Echo from 'laravel-echo'

window.Echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: 443,
  wssPort: 443,
  forceTLS: true,
  enabledTransports: ['ws', 'wss'],
})

// Escuchar eventos
Echo.channel('show.1')
  .listen('PlayerEliminated', (e) => {
    console.log(`Player ${e.player.number} eliminated`)
  })
```

## Rate Limiting

```php
// routes/channels.php
Broadcast::channel('game.{type}', function ($user, $type) {
    return $user->status === 'alive';
}, ['throttle' => 'ws:10,1']); // 10 mensajes por segundo
```

## Docker + Traefik

Reverb usa **el mismo dominio y puerto** que Laravel (443/WSS).

```yaml
labels:
  - "traefik.enable=true"
  - "traefik.http.routers.api.rule=Host(`api.tudominio.com`)"
  - "traefik.http.services.api.loadbalancer.server.port=9000"
```

Traefik detecta automáticamente WebSocket upgrade.

**Ver también**: `docker-deployment.md`
