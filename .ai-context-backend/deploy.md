# Deploy notes — Reverb & WebSockets (Resumen)

**Resumen:**
Se eliminaron las variables `REVERB_SERVER_*` (no usadas por Laravel Reverb) y se agregó `REVERB_PATH=/ws`. También se agregó un `location /ws` en Nginx para proxyar WebSocket a Reverb. Actualiza `VITE_*` para que en desarrollo apunten explícitamente a `localhost` y no hereden del backend en producción.

## Valores esperados en `.env` (local)
- BROADCAST_CONNECTION=reverb
- REVERB_APP_ID=691690
- REVERB_APP_KEY=tfbhkkj8eseagwzr45uv
- REVERB_APP_SECRET=7hhlcremkluk75rlzi6f
- REVERB_HOST=127.0.0.1
- REVERB_PORT=8080
- REVERB_SCHEME=http
- REVERB_PATH=/ws

## Vite (local)
- VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
- VITE_REVERB_HOST=localhost
- VITE_REVERB_PORT=8080
- VITE_REVERB_SCHEME=http
- VITE_REVERB_PATH=/ws

> **Nota:** En producción, no heredes Vite de backend; coloca `VITE_REVERB_HOST`/`PORT`/`SCHEME` apropiados para el dominio TLS (ej. host=api.midominio.com, port=443, scheme=https, path=/ws).

## Nginx — mínimo indispensable (ejemplo)

location /ws {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;

    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;

    proxy_read_timeout 3600;
    proxy_send_timeout 3600;
}

## Reverb worker
Mantener: `php /app/artisan reverb:start --host=0.0.0.0 --port=8080` (interno, sin TLS).

## Flujo de red (correcto)
Browser (wss://) → Traefik (TLS termination) → Nginx (http, proxy /ws) → Reverb (ws://127.0.0.1:8080)

## Checklist
- [x] Eliminé `REVERB_SERVER_*`
- [x] `REVERB_HOST=127.0.0.1` (no `localhost` en backend)
- [x] Agregué `REVERB_PATH=/ws`
- [x] `nginx.template.conf` incluye `location /ws` que proxya a `127.0.0.1:8080`
- [x] No se expone el puerto 8080 externamente (Traefik lo maneja internamente)
- [x] `VITE_*` locales son explícitos (no heredar backend en producción)

---

## Cambios recomendados para Dokploy / Producción
1. **Secrets**: No almacenar secretos en archivos dentro del repo; usar el secret store de Dokploy (DB, Redis, RustFS keys, ElevenLabs API key, etc.).
2. **Backend env (Dokploy service)**: asegurar valores internos:
   - `REVERB_HOST=127.0.0.1`
   - `REVERB_PORT=8080`
   - `REVERB_PATH=/ws`
   - `SESSION_SECURE_COOKIE=true`
   - `SESSION_DOMAIN=.yourdomain.tld`
   - `LOG_LEVEL=info`
3. **Frontend env (hosting/build)**: definir `VITE_REVERB_HOST`/`PORT`/`SCHEME` en la configuración del hosting/frontend pipeline (NO heredar del backend). Ejemplo: `VITE_REVERB_HOST=api.ruleta.jemg.dev`, `VITE_REVERB_PORT=443`, `VITE_REVERB_SCHEME=https`, `VITE_REVERB_PATH=/ws`.
4. **Ingress / Traefik**: TLS termination en Traefik. Rutas:
   - `Host api.ruleta.jemg.dev` -> Traefik -> nginx app (HTTP)
   - `Path /ws` proxied by nginx to `http://127.0.0.1:8080`
5. **Nginx**: no abrir puerto 8080 al exterior; internal proxy only.

## Verificación rápida (post-deploy)
- `nginx -t` inside the image / container
- Verify Reverb worker is running (supervisor log / `ps`) and listening on 127.0.0.1:8080
- From a test container: `curl -i -N -H "Connection: Upgrade" -H "Upgrade: websocket" http://127.0.0.1:8080/ws` should not return 404
- From the browser: connect to `wss://api.ruleta.jemg.dev/ws` and check the WebSocket handshake in devtools Network tab

### Small Reverb check script (local / CI)
You can use the included Node script to verify a Reverb websocket handshake from CI or a test runner.

1. Install (locally / CI):

   npm install ws --no-save

2. Run:

   node scripts/check-reverb.js "wss://api.ruleta.jemg.dev/ws/app/tfbhkkj8eseagwzr45uv?protocol=7"

The script exits `0` on success, non-zero on failure (timeout, handshake error, or network error). This is handy to add as a pre-deploy or post-deploy smoke test.

### Echo / client snippet (example)
Use the application *key* and the `/ws` path. Example with `laravel-echo`:

```js
import Echo from 'laravel-echo'

window.Echo = new Echo({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT,
  wssPort: import.meta.env.VITE_REVERB_PORT,
  forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
  enabledTransports: ['ws', 'wss'],
  path: import.meta.env.VITE_REVERB_PATH // -> '/ws'
})
```

This ensures the client connects to `/ws/app/{key}` automatically.


---
