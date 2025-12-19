# Game Goals - Ruleta Familiar

## Objetivo General

Diseñar 4 juegos eliminatorios que:
- Sean **justos** (servidor decide)
- Sean **comprensibles** (reglas claras)
- Sean **dinámicos** (mantienen tensión)
- Sean **escalables** (funcionan con 5 o 50 jugadores)
- Sean **divertidos** (incluso para eliminados como espectadores)

## Los 4 Juegos

### 1. Juego del Millonario

**Tipo**: Trivia competitiva en tiempo real  
**Inspiración**: "¿Quién quiere ser millonario?" (Caracol TV)  
**Eliminación**: Progresiva por cooldown y errores acumulados

#### Mecánica

1. **Pregunta global**: Todos los jugadores vivos ven la misma pregunta
2. **Respuesta múltiple**: 4 opciones (A, B, C, D)
3. **Sistema de puntos**:
   - Respuesta correcta rápida: +3 puntos
   - Respuesta correcta lenta: +1 punto
   - Respuesta incorrecta: -2 puntos + cooldown de 1 pregunta
4. **Cooldown**: Si fallas, no puedes responder la siguiente pregunta
5. **Eliminación**: Al finalizar ronda, los X jugadores con menor puntaje son eliminados

#### Objetivos de Diseño

- ✅ **Justo**: Velocidad + conocimiento
- ✅ **Tenso**: Cooldown crea presión
- ✅ **Escalable**: Todos juegan simultáneamente
- ✅ **Control**: Laravel valida respuestas

#### Eliminación Matemática

Ejemplo con 30 jugadores:
- Se hacen 10 preguntas
- Al final, se eliminan los 5 con menor puntaje
- Quedan 25 para el siguiente juego

**Fórmula**: Eliminar ~15-20% de jugadores actuales

#### Reglas Especiales

- Si hay empate en último lugar, nadie es eliminado de esos
- Mínimo 3 segundos por pregunta
- Máximo 15 segundos por pregunta
- Supervisores pueden anular preguntas mal formuladas

---

### 2. La Cuerda

**Tipo**: Competencia grupal de clicks  
**Inspiración**: Tug of war (juego de la cuerda)  
**Eliminación**: Grupos completos

#### Mecánica

1. **Formación de grupos**: Sistema divide jugadores en grupos de 5
2. **Competencia simultánea**: Todos los grupos compiten a la vez
3. **Sistema de clicks**: 
   - Cada click suma +1 al "poder" de tu grupo
   - El grupo opuesto se defiende clickeando también
4. **Visual**: Cuerda en 3D (Three.js) muestra tensión
5. **Límite rojo**: Si la cuerda cruza la línea roja de un grupo, ese grupo pierde
6. **Eliminación**: TODOS los miembros del grupo perdedor son eliminados

#### Objetivos de Diseño

- ✅ **Cooperativo**: Trabajas con tu grupo
- ✅ **Visual**: Three.js hace la tensión evidente
- ✅ **Rápido**: ~60-90 segundos por ronda
- ✅ **Dramático**: Eliminación grupal aumenta stakes

#### Formación de Grupos

**Regla general**: Grupos de 5

| Jugadores | Distribución |
|-----------|--------------|
| 25 | 5 grupos de 5 |
| 23 | 4 grupos de 5 + 1 grupo de 3 |
| 18 | 3 grupos de 5 + 1 grupo de 3 |
| 17 | 3 grupos de 5 + 1 de 2 (se balancea manual) |

**Decisión**: Laravel crea grupos automáticamente, priorizando balance

#### Eliminación Matemática

- Se eliminan N-1 grupos (todos menos el ganador)
- O se pueden hacer rondas eliminatorias (semifinales, final)

**Ejemplo**: 25 jugadores (5 grupos)
- Ronda 1: 3 grupos ganan → 15 jugadores
- Ronda 2: 1 grupo gana → 5 jugadores

#### Reglas Especiales

- Grupos se balancean por habilidad percibida (opcional)
- Máximo 90 segundos por ronda
- Si empate técnico (raro), se repite

#### Visual (Three.js)

- Cuerda en el centro
- Marca roja en cada extremo
- Cuerda se mueve según ratio de clicks
- Indicador de "poder" de cada grupo

---

### 3. Deletréalo

**Tipo**: Deletreo bajo presión + validación por supervisores  
**Inspiración**: SquidCraft + Spelling Bee  
**Eliminación**: Individual por fallo o timeout

#### Mecánica

1. **Selección aleatoria**: Ruleta elige a 1 jugador
2. **Palabra asignada**: Sistema da palabra del diccionario español
3. **Tiempo límite**: 15-20 segundos para deletrear
4. **Grabación**: Jugador graba audio deletreando
5. **Envío**: Audio se sube a S3 y BD
6. **Validación**: Supervisor escucha y valida
7. **Resultado**:
   - ✅ Correcto → jugador continúa
   - ❌ Incorrecto o timeout → jugador eliminado

#### Objetivos de Diseño

- ✅ **Tenso**: Visual de globo inflándose
- ✅ **Justo**: Supervisores humanos validan (evita bugs de reconocimiento automático)
- ✅ **Auditable**: Todos los audios guardados
- ✅ **Individual**: Presión máxima en el jugador

#### Sistema de Palabras

**Dificultad progresiva**:
- Ronda 1: Palabras de 6-8 letras
- Ronda 2: Palabras de 9-12 letras
- Diccionario: DRAE (Español colombiano/mexicano neutro)

**Ejemplo de palabras**:
- Ronda 1: `murciélago`, `excelente`, `horizonte`
- Ronda 2: `responsabilidad`, `extraordinario`, `inexplicable`

#### Visual (Globo)

- Globo animado (CSS/Canvas)
- Se infla linealmente durante el tiempo límite
- Explota si se acaba el tiempo
- Sincronizado con countdown

#### Flujo Completo

```
Ruleta selecciona jugador
    ↓
"Jugador #23, deletrea: MURCIÉLAGO"
    ↓
Globo empieza a inflarse
    ↓
Jugador graba: "M-U-R-C-I-É-L-A-G-O"
    ↓
Audio se sube
    ↓
Supervisor escucha
    ↓
Supervisor valida → ✅ Correcto
    ↓
Jugador pasa
    ↓
Siguiente jugador
```

#### Eliminación Matemática

- 1 jugador por ronda
- Rondas rápidas (~30 segundos cada una)
- Se eliminan 5-8 jugadores en total (según cantidad de jugadores activos)

#### Reglas Especiales

- Si supervisor marca como "dudoso", puede pedir repetición (solo 1 vez)
- Si audio no se sube por falla técnica, se da segunda oportunidad
- Jugador puede saltarse acentos SI deletrea "con tilde" o "con acento"

---

### 4. La Ruleta Final

**Tipo**: Acumulación de puntos por suerte + estrategia  
**Inspiración**: Ruletas de casino + The Price is Right  
**Eliminación**: Todos menos el ganador

#### Mecánica

1. **Todos los sobrevivientes participan**: No importa si son 3, 5, 8 o 15
2. **Turnos**: Los jugadores giran la ruleta por turnos (orden aleatorio)
3. **Ruleta de puntos**: Contiene sectores con valores:
   - +50
   - +100
   - +200
   - +500
   - +1000
   - PIERDE TODO
   - x2
4. **Acumulación**: Puntos se acumulan
5. **Meta**: El primero en llegar a **5000 puntos** gana
6. **Resultado**: TODOS los demás son eliminados
7. **Ganador**: 1 solo ganador del show

#### Objetivos de Diseño

- ✅ **Dramático**: Cualquiera puede ganar
- ✅ **Rápido**: ~5-10 minutos máximo
- ✅ **Justo**: Suerte + timing
- ✅ **Definitivo**: Un solo ganador claro

#### Sectores de la Ruleta

| Sector | Probabilidad | Efecto |
|--------|--------------|--------|
| +50 | 30% | Suma 50 |
| +100 | 25% | Suma 100 |
| +200 | 20% | Suma 200 |
| +500 | 10% | Suma 500 |
| +1000 | 5% | Suma 1000 |
| PIERDE TODO | 5% | Puntos = 0 |
| x2 | 5% | Puntos x 2 |

#### Estrategia

- Los jugadores pueden ver los puntajes de todos
- Decidir si "arriesgarse" con otro giro o "conservar"
- Si caes en "PIERDE TODO", vuelves a 0 (dramático)

#### Visual

- Ruleta animada (CSS/Canvas)
- Tablero de puntuaciones visible para todos
- Efecto sonoro diferente por cada sector
- Celebración visual para el ganador

#### Reglas Especiales

- Límite de 10 segundos para decidir si giras
- Si no decides, giras automáticamente
- Si hay empate exacto en 5000, ambos giran hasta desempate

---

## Eliminación Matemática Total

### Ejemplo con 30 jugadores iniciales

| Juego | Vivos antes | Vivos después | Eliminados |
|-------|-------------|---------------|------------|
| **Inicio** | 30 | 30 | 0 |
| Millonario 1 | 30 | 25 | 5 |
| Deletréalo 1 | 25 | 20 | 5 |
| La Cuerda | 20 | 12 | 8 |
| Millonario 2 | 12 | 10 | 2 |
| Deletréalo 2 | 10 | 7 | 3 |
| **Ruleta Final** | 7 | **1** | **6** |

### Fórmula General

**No importa cuántos lleguen a la Ruleta Final**: Siempre queda 1 ganador.

Esto hace que el sistema sea **flexible** y **matemáticamente correcto** sin importar desconexiones, números impares, etc.

---

## Intercalado de Juegos

Los juegos se intercalan para mantener variedad:

```
1. Millonario (trivia)
2. Deletréalo (individual)
3. La Cuerda (grupal, física)
4. Millonario (trivia de nuevo)
5. Deletréalo (individual de nuevo)
6. Ruleta Final (azar + estrategia)
```

**Por qué este orden**:
- Alterna mental/físico
- Alterna grupal/individual
- Aumenta tensión progresivamente
- La Ruleta siempre es final (dramática)

---

## Audio para Cada Juego

### Millonario
- "Pregunta en pantalla"
- "Respuesta correcta"
- "Respuesta incorrecta"
- "Estás en cooldown"

### La Cuerda
- "Prepárense para la cuerda"
- "El grupo [X] ha perdido"
- "Todos eliminados"

### Deletréalo
- "Jugador número [X], deletrea: [palabra]"
- "Correcto, sigues vivo"
- "Incorrecto, eliminado"
- "Tiempo agotado"

### Ruleta Final
- "Gira la ruleta"
- "+50", "+100", etc.
- "¡Pierdes todo!"
- "¡Tenemos ganador!"

---

## Conclusión

Estos 4 juegos están diseñados para:
- Ser justos y comprensibles
- Eliminar progresivamente
- Mantener tensión y emoción
- Funcionar con números variables de jugadores
- Dejar siempre **1 solo ganador**

Cada juego complementa al otro, y el orden está pensado para máxima diversión y drama.

---

**Próximo paso**: Revisa `technical-goals.md` para objetivos de implementación técnica o `knowledge/game-implementation.md` para detalles de código.
