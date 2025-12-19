# Technical Goals - Ruleta Familiar Backend

## Objetivos Técnicos Generales

Este documento define los objetivos técnicos, métricas de performance, y decisiones arquitectónicas del backend de Ruleta Familiar.

---

## 1. Performance y Escalabilidad

### Jugadores Simultáneos
- **Objetivo**: 50 jugadores concurrentes
- **Mínimo aceptable**: 30 jugadores
- **Stretch goal**: 100 jugadores

### Latencia WebSocket
- **Objetivo**: < 200ms
- **Aceptable**: < 500ms
- **Crítico**: > 1000ms (inaceptable)

### Throughput
- **Eventos por segundo**: 1000+ eventos/s
- **Broadcast masivo**: < 2 segundos para llegar a todos los clientes

### Tiempo de Reconexión
- **Objetivo**: < 3 segundos
- **Con PIN**: < 5 segundos

---

## 2. Confiabilidad

### Uptime
- **Objetivo**: 99.5% durante partida activa
- **Tolerancia a fallos**: Reconexión automática

### Persistencia
- **Estado del juego**: Guardado cada 30 segundos
- **Audios**: 100% persistidos en S3
- **Stat cards**: Persistidos inmediatamente tras cambio

### Recuperación
- **Desconexión**: Reconexión con PIN sin pérdida de estado
- **Crash del servidor**: Estado recuperable desde BD/Redis

---

## 3. Seguridad

### Anti-Trampas
- ✅ **Servidor autoritativo**: Cliente NUNCA decide resultados
- ✅ **Validación**: Todas las acciones validadas en backend
- ✅ **Rate limiting**: Máximo 10 acciones/segundo por jugador
- ✅ **Timeout**: Respuestas tardías rechazadas

### Autenticación
- **PIN de reconexión**: 4 dígitos (0000-9999)
- **Sesión**: Laravel Sanctum (tokens)
- **WebSocket auth**: Validación en conexión

### Autorización
- **Roles claros**: Player vs Supervisor
- **Permisos granulares**: Supervisores pueden validar, players solo jugar
- **Auditoría**: Logs de todas las acciones críticas

---

## 4. Arquitectura

### Principio: Servidor Autoritativo

**Laravel es la única fuente de verdad**:
- Calcula resultados
- Valida acciones
- Decide eliminaciones
- Controla flujo del show

**Vue solo**:
- Envía input
- Renderiza estado
- Reproduce audio/visuales

### Separación de Responsabilidades

```
Laravel (Backend)
├── Lógica de negocio
├── Validación
├── Persistencia
├── WebSockets (Reverb)
└── Autoridad

Vue (Frontend)
├── UI/UX
├── Input
├── Animaciones
└── Renderizado
```

### State Machine

El show es una **máquina de estados finita**:

```
LOBBY → INTRO → MILLIONAIRE → SPELL → ROPE → 
MILLIONAIRE → SPELL → ROULETTE → WINNER
```

- Solo Laravel cambia estados
- Vue escucha `ShowStateChanged`
- No hay estados inconsistentes

---

## 5. Audio System

### Generación
- **Servicio**: ElevenLabs o TopMediai
- **Dónde**: Backend ONLY (seguridad + caching)
- **Formato**: MP3 44.1kHz 128kbps

### Almacenamiento
- **Storage**: RustFS (S3-compatible)
- **Cache**: Redis (URLs firmadas)
- **TTL**: 24 horas para URLs firmadas

### Reutilización
- **Números 1-50**: Generados una vez, reutilizados siempre
- **Diálogos del sistema**: Precargados en seed
- **Audios de jugadores**: Guardados para auditoría

### Performance
- **Precarga**: Audios comunes descargados en LOBBY
- **Streaming**: Audios largos (>30s) streameados
- **Fallback**: Si falla audio, juego continúa (solo se pierde inmersión)

---

## 6. WebSockets (Reverb)

### Conexión
- **Protocolo**: WSS (WebSocket Secure)
- **Puerto**: 443 (mismo que HTTPS)
- **Dominio**: Mismo que API (api.tudominio.com)

### Canales

#### Público
- `show.{id}` - Estado general del show

#### Privado
- `player.{id}` - Notificaciones personales
- `game.{type}.{room}` - Estado del juego actual

#### Presence
- `supervisor.show` - Supervisores en línea

### Eventos Clave

```typescript
// Server → Client
PlayerJoined
PlayerEliminated
PlayerPassed
ShowStateChanged
GameStarted
AudioRequested
ChatMessageSent

// Client → Server
PlayerAction
ChatMessage
```

### Rate Limiting
- **Acciones de juego**: 10/segundo
- **Chat**: 1 mensaje/segundo
- **Reconexión**: 5 intentos/minuto

---

## 7. Base de Datos

### Tecnología
- **Motor**: MySQL 8
- **Cache**: Redis
- **Search**: (Futuro) Meilisearch para replay

### Optimización
- **Índices**: En `players.number`, `players.code`, `players.status`
- **Particionamiento**: (Futuro) Histórico de partidas por fecha
- **Réplicas**: (Futuro) Read replicas para supervisores

### Backups
- **Frecuencia**: Cada 6 horas
- **Retención**: 7 días
- **Crítico**: Audios en S3 con versionado

---

## 8. Docker y Deployment

### Contenedores
```
laravel-app     (PHP 8.3 + Reverb)
queue-worker    (Laravel queues)
redis           (Cache + sessions)
mysql           (Base de datos)
```

### Dokploy + Traefik
- **Proxy**: Traefik (automático con Dokploy)
- **SSL**: Let's Encrypt (automático)
- **Dominio**: Configurado en Dokploy
- **Escalado**: Horizontal (múltiples instancias de laravel-app)

### CI/CD
- **Git push** → Dokploy detecta → Build → Deploy automático
- **Zero downtime**: Rolling deployment
- **Rollback**: 1 click en Dokploy

---

## 9. Monitoreo y Observabilidad

### Métricas Clave
- Jugadores conectados
- Latencia promedio WS
- Tasa de errores
- Eventos por segundo
- Uso de CPU/RAM

### Logs
- **Aplicación**: Laravel log
- **WebSocket**: Reverb log
- **Auditoría**: Acciones críticas (eliminaciones, validaciones)

### Alertas (Futuro)
- Latencia > 1000ms
- Desconexiones masivas
- Errores críticos

---

## 10. Testing

### Unidad
- Lógica de juegos (eliminaciones, puntuaciones)
- Validaciones
- State machine

### Integración
- API endpoints
- WebSocket eventos
- Audio upload

### Carga
- 50 jugadores simultáneos
- 1000 eventos/segundo
- Reconexiones masivas

### E2E (Futuro)
- Flujo completo de partida
- Supervisión
- Chat

---

## 11. Objetivos de Código

### Organización
- **Domain-Driven**: Estructura por dominio (Player, Game, Audio, etc.)
- **Actions**: Acciones atómicas reutilizables
- **Services**: Lógica compleja aislada
- **Events**: Broadcasting desacoplado

### Calidad
- **PSR-12**: Code style
- **Type hints**: Strict types
- **Comments**: Solo cuando necesario
- **Tests**: Coverage > 70% en lógica crítica

### Performance
- **Eager loading**: Evitar N+1
- **Caching**: Redis para datos frecuentes
- **Queue**: Jobs pesados (audio, notificaciones)

---

## 12. Stack Tecnológico Completo

### Backend
- **Framework**: Laravel 12
- **PHP**: 8.3+
- **Database**: MySQL 8
- **Cache**: Redis 7
- **WebSocket**: Laravel Reverb
- **Queue**: Redis
- **Storage**: RustFS (S3)

### Audio
- **TTS**: TopMediai (configurado)
- **Fallback**: ElevenLabs (si TopMediai falla)
- **Format**: MP3 44.1kHz 128kbps

### Infrastructure
- **Containerization**: Docker
- **Orchestration**: Dokploy
- **Proxy**: Traefik
- **SSL**: Let's Encrypt
- **DNS**: Cloudflare (recomendado)

### Frontend (separado)
- **Framework**: Vue 3
- **Build**: Vite
- **WebSocket Client**: laravel-echo
- **3D**: Three.js + WebGPU
- **UI**: Tailwind CSS

---

## 13. Decisiones Arquitectónicas Clave

### ✅ Dos Repositorios
- `ruleta-api` (backend)
- `ruleta-web` (frontend)
- **Por qué**: Desacoplamiento, escalabilidad, mobile futuro

### ✅ Servidor Autoritativo
- Laravel decide TODO
- **Por qué**: Anti-trampas, consistencia, justo

### ✅ Audios Reutilizables
- Números y diálogos precargados
- **Por qué**: Costos, performance, auditable

### ✅ Reconexión por PIN
- 4 dígitos
- **Por qué**: WiFi inestable en contexto familiar

### ✅ Supervisión Humana (Deletréalo)
- No reconocimiento automático de voz
- **Por qué**: Precisión, evita bugs, experiencia familiar

### ✅ WebGPU + Three.js Solo Visual
- No afecta lógica
- **Por qué**: Separación de responsabilidades, debugging

---

## 14. Objetivos de Experiencia del Desarrollador

### Local Development
- `docker-compose up` → Todo funciona
- Hot reload en Vue
- Logs claros
- Reverb visible en consola

### Documentación
- Contexto IA (este folder)
- README técnico
- Diagramas de flujo
- API docs (futuro)

### Onboarding
- Cualquier dev puede arrancar el proyecto en < 30 minutos
- Arquitectura clara
- Decisiones documentadas

---

## 15. Métricas de Éxito Técnico

Una implementación exitosa cumple:

- ✅ 50 jugadores sin lag
- ✅ Latencia WS < 200ms
- ✅ Reconexión funciona en 90%+ de casos
- ✅ Audios se reproducen < 2s tras evento
- ✅ Zero crashes durante partida
- ✅ Logs auditables
- ✅ Deploy < 5 minutos
- ✅ Rollback < 2 minutos

---

## 16. Deuda Técnica Aceptable (Fase 1)

**Está OK NO tener** (por ahora):
- ❌ Replay de partidas
- ❌ Analytics avanzados
- ❌ Multi-idioma
- ❌ Mobile app nativa
- ❌ Escalado automático
- ❌ A/B testing

**Pero SÍ tener**:
- ✅ Logs básicos
- ✅ Backups
- ✅ Monitoreo básico
- ✅ Documentación

---

## 17. Próximos Pasos Técnicos

### Inmediato
1. Migraciones completas
2. Seeders de audios
3. Modelo Player + auth
4. Canal WebSocket básico

### Corto Plazo
1. Implementar State Machine
2. Juego del Millonario (completo)
3. Sistema de audio (TopMediai integration)
4. Chat básico

### Mediano Plazo
1. Resto de juegos
2. Panel de supervisores
3. Reconexión por PIN
4. Visual Three.js (La Cuerda)

---

## Conclusión

Los objetivos técnicos están balanceados entre:
- **Ambición**: 50 jugadores, audio dinámico, visuales 3D
- **Realismo**: Arquitectura simple, stack conocido, MVP rápido
- **Profesionalismo**: Código limpio, tests, documentación

Este backend está diseñado para ser **jugable**, **mantenible** y **escalable**.

---

**Próximo paso**: Revisa `knowledge/architecture.md` para arquitectura detallada o `knowledge/backend-structure.md` para estructura de carpetas.
