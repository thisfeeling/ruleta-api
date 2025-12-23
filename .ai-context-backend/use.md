# use.md — Guía de uso e integración: `ruleta-api` → `ruleta-web`

## ✨ Introducción

**Propósito:** Este documento explica cómo el frontend (`ruleta-web`) debe consumir el backend (`ruleta-api`) — endpoints REST, eventos Reverb (WebSockets), servicios críticos (TTS / Storage / Audit), y flujos concretos (join, reconnect, play audio, submit action).

**Arquitectura breve:** servidor autoritativo (Laravel 12) + Reverb (WebSocket) para sincronización en tiempo real. Audio TTS vía ElevenLabs y almacenamiento en RustFS (S3 compatible). Documentación técnica en `.ai-context-backend/`.

---

## 🚪 Autenticación y sesión (Sanctum)

- Endpoints principales:
  - `POST /api/auth/player/join` — crear jugador / asignar número + PIN, devuelve `{ user, player, token, pin }`.
  - `POST /api/auth/player/reconnect` — reconexión por PIN, devuelve nuevo token.
  - `POST /api/auth/login` — login supervisor/admin (devuelve token Sanctum).
  - `GET /api/auth/me`, `POST /api/auth/logout`.

- Headers: `Authorization: Bearer <token>` para requests autenticados.
- PIN flow: el PIN de 4 dígitos se genera por `App\Services\Player\PINGeneratorService` y se usa en `/auth/player/reconnect`.

---

## 🔧 Endpoints REST principales

Resumen de rutas (controllers relevantes entre paréntesis):

- Players & Auth
  - `POST /api/auth/player/join` ( `Auth\PlayerAuthController::join` )
  - `POST /api/auth/player/reconnect` ( `Auth\PlayerAuthController::reconnect` )

- Games
  - `GET /api/games/{game}/state` ( `GameController::state` )
  - `POST /api/games/{game}/action` ( `GameController::action` )
  - `POST /api/games/{game}/complete` (supervisor)

- Instructions
  - `POST /api/games/{game}/instructions/read` (player marca leído)
  - `GET /api/games/{game}/instructions/status`

- Scoreboard & Achievements
  - `GET /api/shows/{show}/scoreboard` ( `ScoreboardController::show` )
  - `GET /api/shows/{show}/scoreboard/top?limit=`
  - `GET /api/scoreboard/me`
  - `GET /api/achievements`, `GET /api/achievements/me`

- Supervisor
  - `POST /api/supervisor/shows/{show}/{start|pause|end}`
  - `POST /api/supervisor/audio/{audioPlay}/approve|reject`

Notas:
- Consulta `routes/api.php` para rutas actuales y `openapi.yaml` para la especificación parcial.
- Falta documentar/implementar: endpoints para upload de audio (Spell) y endpoints de auditoría (`/api/supervisor/audit-logs`) — ver sección Roadmap.

---

## 🌐 WebSockets (Reverb) — canales y eventos que el cliente debe suscribir

Canales recomendados:
- `show.{showId}` — eventos de flujo global del show
- `game.{gameId}` — eventos particulares al juego
- `player.{playerId}` — eventos privados del jugador
- `supervisor.{showId}` — feed de auditoría y validaciones (solo supervisores)
- `presence.show.{showId}` — presencia (jugadores conectados)

Eventos esenciales (ejemplos de payload):
- `show.started` — `{ show_id, started_at, current_phase }`
- `show.phase_changed` — `{ from_phase, to_phase }`
- `game.started` — `{ game_id, game_type }`
- `millionaire.question_displayed` — `{ question: { id, question_number, time_limit_seconds } }`
- `player.eliminated` — `{ player_id, player_number, reason }`
- `audio.track_started` — `{ track_key, channel, meta }`
- `scoreboard.score_added` — `{ player_id, raw_score, normalized_score }`
- `achievement.unlocked` — `{ player_id, achievement_key }`
- `instructions.required` / `instructions.completed`

Referencia: eventos definidos en `app/Events/` y documentación en `.ai-context-backend/reverb-websockets.md`.

---

## 🧭 Servicios críticos que consume el Frontend

- TTS: `App\Services\TTS\TTSService` — genera voz (ElevenLabs). Uso: generar audios del narrador y números (seeders para 1–50).
- Storage: `App\Services\Storage\StorageService` — subida y *signed URLs* para reproducción segura.
- Audio: `App\Services\Audio\AudioService` y modelo `AudioTrack`/`AudioPlay` — tracking de reproducciones, aprobaciones por supervisor.
- Instructions: `App\Services\Instruction\InstructionService` — requiere y marca instrucciones; emite `InstructionsRequired`.
- Scoreboard: `App\Services\Scoreboard\ScoreboardService` — normaliza puntajes (0–1000) y emite eventos.
- Audit: `App\Services\Audit\AuditService` — registra eventos en DB y envía backups a S3; emite `AuditLogCreated`.
- PIN generator: `App\Services\Player\PINGeneratorService` — reconexión por PIN.

Env vars relevantes (mínimo): `ELEVENLABS_API_KEY`, `ELEVENLABS_VOICE_ID`, `RUSTFS_*`, `BROADCAST_CONNECTION=reverb`, `REVERB_HOST`, `REVERB_PORT`, `ORIGINS` (CORS).

---

## ⚡ Flujos de ejemplo (rápidos)

1) Join (player):

Request
```
POST /api/auth/player/join
{
  "show_id": 1,
  "name": "Ana"
}
```
Response (parcial)
```
{
  "user": {"id": 123, "name": "Ana"},
  "player": {"id": 456, "number": 7},
  "token": "<plainTextToken>",
  "pin": "3241"
}
```
Cliente debe: guardar `token`, suscribirse a `presence.show.1` y `show.1`.

2) Reconnect (PIN):

```
POST /api/auth/player/reconnect { "pin": "3241" }
```
Response: `{ user, player, token }` — re-suscribirse a canales.

3) Submit answer (Millionaire):
```
POST /api/games/{game}/action
{ "action": { "type": "submit_answer", "answer": "B" } }
```
Respuesta: estado del intento → servidor emitirá `millionaire.answer_result` y actualizará scoreboard si aplica.

4) Play audio (client receives `audio.track_started` event):
- Backend incluye `audio_url` o `signed_url` en payload. Ejemplo de payload del evento:

```json
{
  "event": "audio.track_started",
  "data": {
    "track_key": "narrator.player_eliminated_7",
    "channel": "voice",
    "signed_url": "https://s3.example.com/.../narrator.mp3?X-Amz-...",
    "meta": { "duration_ms": 2100 }
  }
}
```

- Reproducción (snippet cliente):

```js
// Suscribir con Laravel Echo (token en Authorization header)
import Echo from 'laravel-echo'
window.Echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  host: `${import.meta.env.VITE_REVERB_HOST}:${import.meta.env.VITE_REVERB_PORT}`,
  auth: {
    headers: { Authorization: `Bearer ${token}` }
  }
})

// Escuchar evento
window.Echo.channel(`show.${showId}`)
  .listen('audio.track_started', (e) => {
    const url = e.signed_url || e.audio_url
    // reproducir con HTMLAudio
    const a = new Audio(url)
    a.play()
    // o usar WebAudio API para mixing/vol control
  })
```

- Reproducción segura: preferir `signed_url` de corta expiración; el cliente debe reproducir directamente desde la URL (no proxiar audio por backend salvo si se requiere).  

- Upload recomendado para Spell (ejemplo API sugerida):
  - `POST /api/games/{game}/spell/{spell_word}/upload` (Autenticado)
  - FormData: `file` (webm/mp3/ogg), `player_id`
  - Respuesta: `{ audio_track: { id, signed_url, duration_ms } }`

Ejemplo curl (para referencia):
```sh
curl -H "Authorization: Bearer $TOKEN" -F "file=@spell_answer.webm" https://api.example.com/api/games/42/spell/123/upload
```

(Nota: el endpoint de upload aún no está implementado en backend; está recomendado en el roadmap.)

---

## ✅ Checklist de integración (práctico)

- [ ] Endpoints REST implementados y documentados en OpenAPI: auth, games, scoreboard, achievements, supervisor
- [ ] WebSocket: client autentica con `broadcasting/auth` y se suscribe a `presence.show.{id}`, `show.{id}` y `player.{id}`
- [ ] Audio: signed URLs reproducibles (probado en dev con `php artisan storage:link` o pre-signed URLs)
- [ ] Instructions: client maneja `instructions.required` y marca lectura con `/games/{game}/instructions/read`
- [ ] PIN reconnection flow probado (reconnect -> new token -> resubscribe)
- [ ] Entornos: `REVERB_*`, `RUSTFS_*`, `ELEVENLABS_*`, `ORIGINS` configurados
- [ ] Tests E2E básicos: join → start show → play audio → submit action → receive events

---

## 🛣 Roadmap y prioridades (Corto plazo)

**Alta prioridad**
- Completar `openapi.yaml` para todos los endpoints públicos (auth, supervisor, scoreboard, achievements).
- Implementar endpoint de *audio upload* para Spell (FormData upload) y documentarlo (ruta, validaciones: `mimes:webm,mp3,ogg|max:5MB`).
- Implementar `AuditLogController` y rutas `/api/supervisor/audit-logs` (list/detail/export).

**Media prioridad**
- Añadir endpoint/admin para previsualizar/generar TTS (opcional) y documentar quotas.
- Documentar signed URL expiry policy (recomendado: 1–5 minutos para audio playback).

**Baja prioridad**
- Añadir política de rate-limiting y esquemas de error estandarizados en OpenAPI.
- Export de snapshot de scoreboard para analytics.

---

## 📎 Referencias y archivos relevantes

- Documentación general: `.ai-context-backend/README_INDEX.md`
- Reverb events: `.ai-context-backend/reverb-websockets.md`
- OpenAPI (parcial): `.ai-context-backend/openapi.yaml`
- Controllers: `app/Http/Controllers/*`
- Services: `app/Services/*`
- Events: `app/Events/*`

---

## 🔧 Comandos útiles

- Migraciones & seeders: `php artisan migrate --seed`
- Reverb (dev): `php artisan reverb:start`
- Queue worker: `php artisan queue:work --queue=audit,default`

---
