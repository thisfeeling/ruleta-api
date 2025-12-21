# Backend Context - Ruleta Familiar Game Show

## Descripción General

Este directorio contiene la documentación contextual del backend del proyecto **Ruleta Familiar**, un sistema de "game show" en tiempo real inspirado en SquidCraft, desarrollado con Laravel 12, Reverb (WebSockets), y diseñado para 30-50 jugadores simultáneos.

## Estructura de la Documentación

```
.ai-context-backend/
├── README_INDEX.md          # Este archivo (índice principal)
├── goals/                   # Objetivos del proyecto
│   ├── project-overview.md  # Visión general del proyecto
│   ├── game-goals.md        # Objetivos de diseño de juego
│   └── technical-goals.md   # Objetivos técnicos y arquitectónicos
├── knowledge/               # Conocimiento técnico
│   ├── architecture.md      # Arquitectura general del backend
│   ├── audio-system.md      # Sistema de audios (3 canales: music/sfx/voice)
│   ├── backend-structure.md # Estructura de carpetas y organización
│   ├── reverb-websockets.md # Implementación de Reverb y WebSockets
│   ├── database-schema.md   # Esquema de base de datos
│   ├── docker-deployment.md # Docker, Dokploy y deployment
│   ├── bonus-games.md       # Sistema de bonus games (Word Search + Flappy)
│   ├── scoreboard-system.md # Sistema unificado de puntuaciones
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

### 5. Características Clave
- Reconexión por PIN (4 dígitos)
- Stat Cards públicas de jugadores
- Sistema de eliminación progresiva matemática
- Chat en tiempo real (texto + emojis)
- Visualización 3D con Three.js/WebGPU (La Cuerda)
- Scoreboard unificado con normalización 0-1000 para todos los juegos
- Bonus games activables por supervisor (no eliminatorios)
- Intensidad de audio adaptativa según estado del juego

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
3. ✅ **WebSockets 100%** - experiencia en tiempo real
4. ✅ **S3 para audios** - escalabilidad y auditoría
5. ✅ **Reconexión por PIN** - WiFi inestable en contexto familiar
6. ✅ **Supervisión integrada** - validación humana cuando es necesaria

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
