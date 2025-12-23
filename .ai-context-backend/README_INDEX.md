# Backend Context - Ruleta Familiar Game Show

## Descripción General

Este directorio contiene la documentación contextual del backend del proyecto **Ruleta Familiar**, un sistema de "game show" en tiempo real inspirado en SquidCraft, desarrollado con Laravel 12, Reverb (WebSockets), y diseñado para 30-50 jugadores simultáneos.

## Estructura de la Documentación

```
.ai-context-backend/
├── openapi.yaml          # Especificación OpenAPI 3.0 del API REST
├── ws-contract.md        # Contrato WebSocket (canales, eventos, payloads)
├── use.md                # Guía de uso e integración (backend → frontend)
├── README_INDEX.md          # Este archivo (índice principal)
├── goals/                   # Objetivos del proyecto
│   ├── project-overview.md  # Visión general del proyecto
│   ├── game-goals.md        # Objetivos de diseño de juego
│   └── technical-goals.md   # Objetivos técnicos y arquitectónicos
├── implementation/          # Guías de implementación paso a paso (18 archivos)
│   ├── 01-project-setup.md         # Laravel 12, Docker, Reverb setup
│   ├── 02-database-schema.md       # Migraciones y seeders (23 tablas)
│   ├── 03-models-relationships.md  # Modelos Eloquent y relaciones
│   ├── 04-core-services.md         # Storage, TTS, Audio, PIN Generator
│   ├── 05-reverb-websocket-setup.md # Canales, eventos, broadcasting (28+ eventos)
│   ├── 06-authentication-authorization.md # Sanctum, policies, middleware
│   ├── 07-game-services-base.md    # Game engine, state machine, eliminación
│   ├── 08-13-all-games.md          # Implementación de los 6 juegos
│   ├── 14-achievement-system.md    # Triggers y unlocking de logros
│   ├── 15-audit-system.md          # Dual storage (DB + S3)
│   ├── 16-instructions-system.md   # Pre-game instructions con tracking
│   ├── 17-scoreboard-system.md     # Normalización y ranking unificado
│   └── 18-testing-strategy.md      # Pest test suite completo
├── knowledge/               # Conocimiento técnico (14 archivos)
│   ├── architecture.md      # Arquitectura general del backend
│   ├── audio-system.md      # Sistema de audios (3 canales: music/sfx/voice)
│   ├── backend-structure.md # Estructura de carpetas y organización
│   ├── reverb-websockets.md # Implementación de Reverb y WebSockets (28+ eventos)
│   ├── database-schema.md   # Esquema de base de datos
│   ├── docker-deployment.md # Docker, Dokploy y deployment
│   ├── bonus-games.md       # Sistema de bonus games (Word Search + Flappy)
│   ├── scoreboard-system.md # Sistema unificado de puntuaciones
│   ├── achievement-system.md # Sistema de logros (35+ achievements)
│   ├── audit-system.md      # Auditoría dual (DB + S3 permanente)
│   ├── game-data-models.md  # Modelos SQL de 6 juegos + scoreboard unificado
│   ├── instructions-system.md # Sistema de instrucciones pre-juego
│   ├── i18n-system.md       # Sistema de internacionalización (es-CO/en-US)
│   └── tech-stack.md        # Stack tecnológico completo
└── rules/                   # Reglas de implementación
    ├── api-contracts.md     # Contratos de API y eventos
    ├── game-implementation.md # Lógica de los 6 juegos (4 eliminatorios + 2 bonus)
    ├── security-guidelines.md # Seguridad y autenticación
    └── state-machine.md     # Máquina de estados del show
```

## Principios Fundamentales

### 1. Arquitectura Autoritativa del Servidor
- **Laravel es la autoridad**: El servidor decide TODO (movimientos, eliminaciones, puntuaciones, estado del juego)
- **Vue solo renderiza**: El cliente nunca toma decisiones críticas
- **Reverb sincroniza**: WebSockets para comunicación en tiempo real

### 2. Sistema de Juegos
El proyecto implementa 6 juegos totales (4 eliminatorios + 2 bonus opcionales):

**Juegos Eliminatorios:**
1. **Juego del Millonario** - Preguntas y respuestas (tipo "¿Quién quiere ser millonario?")
2. **La Cuerda** - Competencia grupal de clicks con visualización 3D
3. **Deletréalo** - Deletreo de palabras con audio y validación por supervisores
4. **La Ruleta** (final) - Ruleta de puntos acumulativos, siempre deja un ganador

**Bonus Games (No Eliminatorios):**
5. **Word Search** - Sopa de letras 15×15 con 12 palabras, compiten por velocidad
6. **Flappy Bird** - Juego de supervivencia tipo Flappy, compiten por tiempo sobrevivido

Los bonus games:
- Se activan manualmente por el supervisor entre fases eliminatorias
- NO eliminan jugadores
- Generan puntajes en tabla unificada (0-1000 normalizado)
- Son opcionales y no bloquean el flujo principal

### 3. Roles del Sistema
- **Player**: Jugador participante (30-50 personas)
- **Supervisor**: Moderador que valida audios, supervisa el juego y puede intervenir

### 4. Sistema de Audios
- **ElevenLabs/TopMediai**: Generación de voz (narrador estilo SquidCraft)
- **RustFS (S3)**: Almacenamiento de audios reutilizables con 3 canales independientes
- **3 Canales**: Música (0.6), SFX (0.8), Voces (1.0) controlables por separado
- **Audios precargados**: Números 1-50, diálogos del sistema, música de fondo
- **Audios de jugadores**: Grabaciones de "Deletréalo" auditables
- **Tracking events**: TrackStarted, TrackEnded, VolumeChanged para integración MusicBox UI
- **Metadata tracking**: Tabla audio_plays para analytics y auditoría

### 5. Sistemas Avanzados
- **Achievement System**: 35+ logros desbloqueables (OnCorrectAnswer, OnGameWon, OnFirstBlood, etc)
- **Audit System**: Dual storage (DB 30 días + S3 permanente) con partitioning automático
- **Instructions System**: Pre-game instructions bilingües con tracking de lectura
- **i18n System**: Español colombiano (default) + English fallback, TTS narración nativa
- **Unified Scoreboard**: Normalización 0-1000 para todos los juegos, tabla player_scores

### 6. WebSocket Events (28+)
El sistema emite 28+ tipos de eventos WebSocket a través de Reverb:
- **Game flow**: GameStarted, GameEnded, PhaseChanged, PlayerEliminated (8 eventos)
- **Achievements**: AchievementUnlocked, AchievementProgress (2 eventos)
- **Instructions**: InstructionsRequired, InstructionsCompleted (2 eventos)
- **Audio tracking**: TrackStarted, TrackEnded, VolumeChanged (3 eventos)
- **Audit**: AuditLogCreated (1 evento)
- **Per-game events**: MillionaireQuestionDisplayed, RopeStateUpdated, RouletteSpinning, WordFound, FlappyCrashed, etc (12+ eventos)

Ver `reverb-websockets.md` para lista completa y payloads detallados.

### 7. Características Clave
- Reconexión por PIN (4 dígitos)
- Stat Cards públicas de jugadores
- Sistema de eliminación progresiva matemática
- Chat en tiempo real (texto + emojis)
- Visualización 3D con Three.js/WebGPU (La Cuerda)
- Scoreboard unificado con normalización 0-1000 para todos los juegos
- Bonus games activables por supervisor (no eliminatorios)
- Intensidad de audio adaptativa según estado del juego
- 35+ achievements desbloqueables con triggers automáticos
- Auditoría permanente con dual storage (DB + S3)
- Instrucciones pre-juego multilingües con tracking
- Soporte bilingüe (español colombiano / English) en DB y TTS

## Cómo Usar Esta Documentación

### Para IA/Copilot
Cuando trabajes en el backend:
1. **Lee primero** `goals/project-overview.md` para entender la visión
2. **Consulta** `knowledge/architecture.md` para decisiones arquitectónicas
3. **Sigue** `implementation/` paso a paso para construir el proyecto
4. **Revisa** `rules/` antes de implementar cualquier feature
5. **Valida** contra `knowledge/database-schema.md` antes de crear migraciones

### Para Desarrolladores
- Los archivos en `goals/` explican el **QUÉ** y **POR QUÉ**
- Los archivos en `implementation/` explican el **CÓMO PASO A PASO**
- Los archivos en `knowledge/` explican el **CONTEXTO TÉCNICO**
- Los archivos en `rules/` definen los **LÍMITES** y **ESTÁNDARES**

## Guía de Implementación - Orden Recomendado

La carpeta `implementation/` contiene 18 archivos numerados que deben seguirse en orden:

**Status Legend**: [ ] Not Started | [~] In Progress | [x] Completed | [✓] Tested

### Phase 1: Foundation (Steps 1-6)
1. [ ] **[Project Setup](implementation/01-project-setup.md)** - Laravel 12, Docker, Reverb, environment
2. [ ] **[Database Schema](implementation/02-database-schema.md)** - 23 tablas, migraciones, seeders
3. [ ] **[Models & Relationships](implementation/03-models-relationships.md)** - Eloquent models, relations, scopes
4. [ ] **[Core Services](implementation/04-core-services.md)** - Storage S3, TTS, Audio, PIN Generator
5. [ ] **[Reverb WebSocket Setup](implementation/05-reverb-websocket-setup.md)** - 28+ eventos, canales
6. [ ] **[Authentication & Authorization](implementation/06-authentication-authorization.md)** - Sanctum, policies

### Phase 2: Game Engine (Steps 7-13)
7. [ ] **[Game Services Base](implementation/07-game-services-base.md)** - Game engine, state machine
8-13. [ ] **[All Game Implementations](implementation/08-13-all-games.md)** - 6 juegos completos

### Phase 3: Advanced Systems (Steps 14-17)
14. [ ] **[Achievement System](implementation/14-achievement-system.md)** - 35+ logros con triggers
15. [ ] **[Audit System](implementation/15-audit-system.md)** - Dual storage (DB + S3)
16. [ ] **[Instructions System](implementation/16-instructions-system.md)** - Pre-game instructions
17. [ ] **[Scoreboard System](implementation/17-scoreboard-system.md)** - Normalización 0-1000

### Phase 4: Quality (Step 18)
18. [ ] **[Testing Strategy](implementation/18-testing-strategy.md)** - Pest test suite completo

## Cómo Usar Esta Documentación

### Para IA/Copilot
Cuando trabajes en el backend:
1. **Lee primero** `goals/project-overview.md` para entender la visión
2. **Consulta** `knowledge/architecture.md` para decisiones arquitectónicas
3. **Revisa** `rules/` antes de implementar cualquier feature
4. **Valida** contra `knowledge/database-schema.md` antes de crear migraciones

### Para Desarrolladores
- Los archivos en `goals/` explican el **QUÉ** y **POR QUÉ**
- Los archivos en `knowledge/` explican el **CÓMO**
- Los archivos en `rules/` definen los **LÍMITES** y **ESTÁNDARES**

## Stack Tecnológico Resumido

- **Backend**: Laravel 12 (PHP 8.3)
- **WebSockets**: Laravel Reverb
- **Database**: MySQL 8
- **Cache/Queue**: Redis
- **Storage**: RustFS (S3-compatible)
- **Audio**: ElevenLabs API / TopMediai API
- **Containerization**: Docker
- **Deployment**: Dokploy + Traefik
- **Frontend**: Vue 3 (repositorio separado)

## Repositorios

Este proyecto sigue arquitectura de **dos repositorios**:
- `ruleta-api` (backend - este repositorio)
- `ruleta-web` (frontend - Vue 3)

Reverb NO es un repositorio separado, vive dentro de Laravel.

## Decisiones Arquitectónicas Críticas

1. ✅ **Servidor autoritativo** - seguridad y consistencia
2. ✅ **Audios reutilizables** - costos y performance
3. ✅ **WebSockets 100%** - experiencia en tiempo real (28+ eventos)
4. ✅ **S3 para audios** - escalabilidad y auditoría permanente
5. ✅ **Reconexión por PIN** - WiFi inestable en contexto familiar
6. ✅ **Supervisión integrada** - validación humana cuando es necesaria
7. ✅ **Achievement system** - gamificación y engagement (35+ logros)
8. ✅ **Dual audit storage** - compliance y analytics (DB 30d + S3 forever)
9. ✅ **Internacionalización** - español colombiano default + English fallback
10. ✅ **Scoreboard unificado** - normalización 0-1000 cross-game

## Estado del Proyecto

📍 **Fase**: Diseño y planificación inicial
🎯 **Objetivo**: Juego familiar jugable con 30-50 personas
🏗️ **Arquitectura**: Definida y documentada
📝 **Próximos pasos**: Implementación de base de datos y seeders

## Contacto y Contexto

Este es un proyecto **familiar** para jugar entre familia y amigos, pero diseñado con arquitectura profesional y escalable.

---

**Última actualización**: Diciembre 2025
**Versión**: 1.0 - Diseño inicial
