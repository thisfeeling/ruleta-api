# Contrato WebSocket — Reverb (ruleta-api)

**Propósito:** Documento formal del contrato WebSocket entre `ruleta-api` (backend) y `ruleta-web` (frontend). Describe canales, eventos, payloads de referencia, patrones de suscripción, autenticación, reconexión y buenas prácticas.

> Nota: Reverb se usa como broadcaster dentro de Laravel. Este contrato debe acompañar al `openapi.yaml` como la fuente de verdad para la comunicación en tiempo real.

---

## Canales (nombres y propósito)

- `presence.show.{showId}` — canal de presencia para una sala/show. Contiene metadata de jugadores conectados y durations.
- `show.{showId}` — eventos globales del show (fases, audios, scoreboard updates, notifications).
- `game.{gameId}` — eventos específicos a una instancia de juego (e.g., Millionaire, Rope).
- `player.{playerId}` — eventos privados dirigidos a un jugador (feedback individual, resultados privados).
- `supervisor.{showId}` — eventos y feed sensibles para supervisores (audit logs, pending audio for review, admin commands).
- `private-presence.{showId}` (opcional) — si se requiere separación estricta entre presencia pública y datos privados.

---

## Autenticación & autorización

- La autenticación usa el endpoint HTTP normal (`/broadcasting/auth`) protegido por Sanctum o el guard habitual.
- Recomendación: enviar `Authorization: Bearer <token>` en la configuración de Echo (cliente). Ejemplo:

```js
window.Echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  host: `${import.meta.env.VITE_REVERB_HOST}:${import.meta.env.VITE_REVERB_PORT}`,
  auth: {
    headers: { Authorization: `Bearer ${token}` }
  }
})
```

- Restricciones de canal:
  - `supervisor.*` solo puede unirse con permisos de supervisor (ver `Policy` en backend).
  - `player.{playerId}` solo accesible por el player (o el supervisor si está autorizado).

---

## Eventos clave y payloads de referencia

Cada evento incluye: nombre, canal(s) destino, propósito, ejemplo de payload y notas. Mantén los payloads pequeños (evita replicar estados completos cuando no es necesario).

### 1) `show.started`
- Canal: `show.{showId}`
- Propósito: indicar que el show inició o cambió de estado importante
- Payload:
```json
{ "show_id": 1, "started_at": "2025-12-23T12:00:00Z", "current_phase": "millennial_round" }
```

### 2) `show.phase_changed`
- Canal: `show.{showId}`
- Propósito: notificar cambio de fase (útil para que la UI cambie de escena)
- Payload:
```json
{ "show_id": 1, "from_phase": "lobby", "to_phase": "millionaire_round", "timestamp": "..." }
```

### 3) `game.started`
- Canal: `show.{showId}` y `game.{gameId}`
- Payload:
```json
{ "game_id": 42, "game_type": "millionaire", "meta": { "time_limit": 45 } }
```

### 4) `millionaire.question_displayed`
- Canal: `show.{showId}` and `game.{gameId}`
- Payload:
```json
{ "game_id": 42, "question": { "id": 9, "question_number": 4, "time_limit_seconds": 30, "text": "¿Cuál...?" } }
```

### 5) `player.eliminated`
- Canal: `show.{showId}`
- Payload:
```json
{ "player_id": 456, "player_number": 7, "reason": "incorrect_answer", "audio_track_key": "narrator.player_eliminated_7" }
```
- Nota: incluir `audio_track_key` o `signed_url` según política de expiración; preferir `track_key` y que el cliente pida `signed_url` por API si es necesario.

### 6) `audio.track_started`
- Canal: `show.{showId}`
- Payload:
```json
{ "track_key": "narrator.player_eliminated_7", "channel": "voice", "signed_url": "https://...", "meta": { "duration_ms": 2100 } }
```

### 7) `scoreboard.score_added`
- Canal: `show.{showId}`
- Payload:
```json
{ "player_id": 456, "player_number": 7, "raw_score": 230, "normalized_score": 450, "metadata": { "game_id": 42 } }
```

### 8) `scoreboard.updated`
- Canal: `show.{showId}`
- Payload: pequeñas representaciones del scoreboard (top N o diff)
```json
{ "top": [ { "player_id": 12, "normalized_score": 900 }, ... ] }
```

### 9) `achievement.unlocked`
- Canal: `show.{showId}`
- Payload:
```json
{ "player_id": 123, "achievement_key": "first_blood", "metadata": {} }
```

### 10) `instructions.required` / `instructions.completed`
- Canal: `show.{showId}`
- Payload:
```json
{ "game_id": 42, "instruction_id": 11, "audio_es_url": "...", "content": "Lea las reglas..." }
```

### 11) `audit.log_created`
- Canal: `supervisor.{showId}`
- Payload: resumo para UI de supervisión
```json
{ "id": 987, "show_id": 1, "event_type": "player_eliminated", "created_at": "...", "payload_summary": "player 7 eliminated" }
```

---

## Recomendaciones de diseño de eventos

- Evitar payloads enormes: enviar claves/IDs y permitir al cliente pedir detalles por REST si necesita data completa.
- Versionar eventos cuando cambie el payload (ej: `scoreboard.score_added:v2` o añadir campo `version`).
- Mantener backward-compatibility hasta un major bump.

---

## Orden, delivery guarantees y deduplicación

- Reverb entrega mensajes en orden por conexión; sin embargo, debido a reintentos y reconexiones, el cliente debe ser idempotente en la aplicación del evento.
- Use `event_id` y `timestamp` para descartar duplicados o aplicar eventos fuera de orden si es necesario.
- Por diseño, el servidor es autoritativo: si hay discrepancia entre un event y el estado obtenido por REST, tomar el REST como fuente de verdad para queries puntuales.

---

## Re-suscripción y reconexión

- Recomendación cliente: al reconectar, volver a suscribir a `presence.show.{showId}` y `show.{showId}`, y pedir un `StateSnapshot` si la reconexión es después de N segundos o si el cliente detecta inconsistencia.
- Backend puede emitir `StateSnapshot` (evento especial) para reposicionar al cliente: `state.snapshot` → `{ show_state: {...}, scoreboard: {...} }`.

---

## Pruebas y mocks

- Proveer un set de fixtures (ver `.ai-context-backend/fixtures/ws/`) con ejemplos de eventos.
- Exponer un `ws-mock` o Postman/Prism mock desde `openapi.yaml` o un script que emita eventos para probar la UI.

---

## Seguridad y privacidad

- Evitar enviar datos sensibles por canales públicos (ej: emails, tokens).
- Auditar suscripciones del supervisor y validar que solo cuentas con permisos vean `supervisor.*`.

---

## Buenas prácticas para el frontend

- Suscribirse solo a los canales necesarios para reducir carga.
- Mantener una cola de eventos y procesarlos de forma que no bloqueen la UI: aplicar updates en background y renderizar en batch.
- Para audio, usar `signed_url` y reproducir desde URL (no proxiar por backend a menos que la política de seguridad lo requiera).

---

## Checklist rápida antes de producción

- [ ] Endpoints `broadcasting/auth` protegidos y funcionando con Sanctum
- [ ] `supervisor.*` restringido por policy
- [ ] `presence` devuelve `user.id`, `player_id`, `number`, `role`, `connected_at`
- [ ] Seeds y fixtures disponibles para pruebas
- [ ] Documentación publicada (.ai-context-backend/ws-contract.md + examples en `.ai-context-backend/fixtures/ws/`)

---

## Archivos relevantes (backend)

- Eventos: `app/Events/**/*`
- Canales: `routes/channels.php`
- Broadcast helpers: `App\Services\Broadcasting\BroadcastService`

---

Si quieres, puedo añadir fixtures de ejemplo en `.ai-context-backend/fixtures/ws/` y generar un pequeño script `scripts/ws_emit_example.php` que emita eventos de ejemplo para que el frontend pueda probar sin que el show esté activo.
