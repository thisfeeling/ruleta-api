# Project Overview - Ruleta Familiar

## Visión del Proyecto

**Ruleta Familiar** es un sistema de "game show" en tiempo real inspirado en la dinámica de SquidCraft, diseñado para ser jugado en contexto familiar con 30-50 participantes simultáneos. El proyecto combina mecánicas de juegos clásicos con tecnología web moderna para crear una experiencia competitiva, social y entretenida.

## Propósito

Crear una plataforma de entretenimiento familiar donde:
- **Jugadores** compiten en 4 juegos eliminatorios diferentes
- **Supervisores** moderan, validan y observan el desarrollo del juego
- **Espectadores** (jugadores eliminados) pueden seguir el show hasta el final
- Todos pueden comunicarse a través de chat en tiempo real

## Inspiración

El proyecto está inspirado en:
- **SquidCraft** (eventos de Twitch/YouTube)
- **Squid Game** (series de Netflix) 
- **Programas de TV**: "¿Quién quiere ser millonario?", "El precio justo"
- **Juegos party**: Fall Guys, Stumble Guys

**IMPORTANTE**: Este proyecto NO tiene relación con Minecraft ni usa assets de SquidCraft. Solo toma el concepto de "game show eliminatorio".

## Modelo de Juego

### Estructura del Show

```
LOBBY (preparación)
    ↓
INTRO (narración + asignación de números)
    ↓
JUEGO DEL MILLONARIO (eliminación progresiva)
    ↓
DELETRÉALO (eliminación individual)
    ↓
LA CUERDA (eliminación grupal)
    ↓
JUEGO DEL MILLONARIO (segunda ronda)
    ↓
DELETRÉALO (segunda ronda)
    ↓
LA RULETA FINAL (un solo ganador)
    ↓
WINNER (celebración)
```

### Progresión de Jugadores

- **Inicio**: 30-50 jugadores
- **Después del Millonario 1**: ~25 jugadores
- **Después de Deletréalo 1**: ~20 jugadores
- **Después de La Cuerda**: ~15 jugadores
- **Después del Millonario 2**: ~10 jugadores
- **Después de Deletréalo 2**: ~5-8 jugadores
- **La Ruleta Final**: Variable (no importa cuántos, siempre queda 1)
- **Final**: 1 GANADOR

## Características Principales

### 1. Sistema de Identidad del Jugador
Cada jugador tiene:
- **Nickname** (elegido por el jugador)
- **Color** (elegido por el jugador)
- **Número** (asignado automáticamente, 1-50)
- **PIN de 4 dígitos** (para reconexión)
- **Gender** (opcional)
- **Status** (alive / eliminated)
- **Role** (player / supervisor)

### 2. Reconexión Robusta
Si un jugador pierde conexión:
- Ve lista de jugadores activos (número + nickname)
- Ingresa su PIN
- Reconecta sin perder progreso
- Solo funciona si sigue vivo

### 3. Sistema de Audio Inmersivo
- **Voz del narrador** (estilo SquidCraft): anuncia eventos, eliminaciones, transiciones
- **Música ambiente**: se adapta a la intensidad del juego
- **Audios reutilizables**: números, diálogos precargados en S3
- **Audios de jugadores**: grabaciones en "Deletréalo" auditables

### 4. Supervisión en Tiempo Real
Los supervisores pueden:
- Ver estado completo del juego
- Validar audios de "Deletréalo"
- Moderar chat
- Pausar/reanudar juegos (opcional)
- Ver estadísticas en vivo

### 5. Chat Familiar
- Solo texto y emojis unicode
- Sin imágenes ni audio
- Moderado por supervisores
- Visible para todos (jugadores y espectadores)

### 6. Visualización 3D (La Cuerda)
- Renderizado con Three.js + WebGPU
- Representa visualmente la competencia de grupos
- Sincronizado con el estado del servidor
- Solo visual, no afecta lógica del juego

## Roles del Sistema

### Player (Jugador)
- Participa en los juegos
- Envía acciones (clicks, respuestas, audios)
- Ve su stat card y la de otros
- Usa el chat
- Si es eliminado, puede seguir como espectador

### Supervisor (Moderador)
- Ve TODO el estado del juego
- Valida audios de "Deletréalo"
- Aprueba o rechaza respuestas de jugadores
- Puede silenciar usuarios en chat
- Inicia juegos/rondas
- NO juega

## Principios de Diseño

### 1. Servidor Autoritativo
**Laravel decide TODO**:
- Quién gana
- Quién es eliminado
- Qué juego sigue
- Cuándo termina una ronda
- Puntuaciones

Vue solo:
- Envía input
- Renderiza estado
- Reproduce audio
- Anima visuales

### 2. Experiencia Familiar
- Diseñado para WiFi doméstico (reconexión por PIN)
- Interfaz clara y simple
- Instrucciones en español colombiano
- Accesible desde móvil y PC
- Sin contenido inapropiado

### 3. Auditable
- Todos los audios guardados en S3
- Logs de eliminaciones
- Historial de partidas
- Stat cards persistentes
- Validación humana (supervisores) cuando es necesaria

### 4. Escalable
- Hasta 50 jugadores simultáneos
- WebSockets eficientes (Reverb)
- Audios cacheados
- Separación backend/frontend
- Docker + Dokploy para deployment

## Flujo Típico de Partida

1. **Pre-juego**
   - Jugadores entran al lobby
   - Eligen nickname y color
   - Sistema asigna número y PIN
   - Supervisores validan que todo esté listo

2. **Inicio**
   - Narrador da bienvenida
   - Se leen las reglas
   - Se inicia el primer juego

3. **Juegos**
   - Laravel emite evento de inicio de juego
   - Jugadores participan
   - Laravel calcula resultados
   - Se anuncia quién pasa y quién es eliminado
   - Screen de "PASASTE" o "ELIMINADO"
   - Transición al siguiente juego

4. **Final**
   - La Ruleta determina el ganador
   - Narrador anuncia ganador
   - Celebración
   - Stat cards finales

5. **Post-juego**
   - Revisión de estadísticas
   - Replay de momentos épicos (opcional)
   - Chat abierto

## Objetivos Técnicos

- ✅ WebSockets 100% (Reverb)
- ✅ Latencia < 200ms
- ✅ 50 jugadores simultáneos
- ✅ Audios precargados < 2 segundos
- ✅ Reconexión automática
- ✅ Compatible mobile + desktop
- ✅ Deploy con Docker
- ✅ S3 para assets pesados
- ✅ Backend autoritativo (anti-trampas)

## Objetivos de Experiencia

- 🎮 Divertido y competitivo
- 👨‍👩‍👧‍👦 Apropiado para familia
- 🎙️ Inmersivo (audio + visual)
- 🏆 Justo (servidor decide)
- 📊 Transparente (stat cards)
- 💬 Social (chat)
- 🔄 Recuperable (reconexión)

## Restricciones

- Máximo 50 jugadores por partida
- Solo texto + emojis en chat
- Supervisión humana obligatoria para "Deletréalo"
- WiFi estable recomendado (pero soporta reconexión)
- Navegador moderno (Chrome 90+, Safari 15+, Firefox 88+)

## Roadmap

### Fase 1: Fundación (actual)
- [x] Diseño de arquitectura
- [x] Documentación contextual
- [ ] Migraciones y modelos
- [ ] Seeders de audios
- [ ] Sistema de autenticación básico

### Fase 2: Juegos Core
- [ ] Implementar "Juego del Millonario"
- [ ] Implementar "La Cuerda" (con Three.js)
- [ ] Implementar "Deletréalo"
- [ ] Implementar "La Ruleta Final"

### Fase 3: Integración
- [ ] State machine completa
- [ ] Sistema de audio (ElevenLabs/TopMediai)
- [ ] Chat en tiempo real
- [ ] Panel de supervisores

### Fase 4: Pulido
- [ ] Reconexión por PIN
- [ ] Stat cards completas
- [ ] Screens de transición
- [ ] Música ambiente adaptativa

### Fase 5: Deploy
- [ ] Docker Compose
- [ ] Dokploy + Traefik
- [ ] Dominio + SSL
- [ ] Testing con familia

## Métricas de Éxito

Un juego exitoso cumple:
- ✅ 30+ jugadores participan sin lag
- ✅ Menos de 3 desconexiones críticas
- ✅ Todos entienden las reglas
- ✅ Supervisores pueden validar audios rápidamente
- ✅ Chat activo y sin spam
- ✅ Ganador claramente definido
- ✅ Experiencia divertida para todos (incluso eliminados)

## Conclusión

Ruleta Familiar es más que un juego: es una plataforma de entretenimiento familiar con arquitectura profesional, diseñada para ser jugable, escalable y divertida. El backend (este repositorio) es el cerebro del sistema, responsable de la lógica, seguridad, y orquestación del show completo.

---

**Próximo paso**: Revisa `game-goals.md` para detalles de cada juego y `technical-goals.md` para objetivos técnicos específicos.
