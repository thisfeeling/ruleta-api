# Audio System - Ruleta Familiar

## Visión General

El sistema de audio es crítico para la experiencia inmersiva del game show. Usa un **sistema de 3 canales independientes** (Music, SFX, Voice) con preloading de assets críticos y cola secuencial para narraciones.

### Componentes Clave
- **3 Canales**: Music (0.6), SFX (0.8), Voice (1.0) con volumen independiente
- **Voz del narrador** (estilo SquidCraft) - Canal Voice
- **Efectos de sonido** por juego - Canal SFX
- **Música ambiente** adaptativa - Canal Music
- **Audios precargados**: Números 1-50, diálogos del sistema
- **Audios de jugadores**: Grabaciones de "Deletréalo" auditables
- **Cola de reproducción**: Narraciones Voice se encolan para evitar superposición

---

## Arquitectura del Sistema de Audio

```
┌─────────────────────────────────────────────────────────────┐
│                    AUDIO PIPELINE                            │
└─────────────────────────────────────────────────────────────┘

1. GENERACIÓN
   ElevenLabs / TopMediai → MP3

2. ALMACENAMIENTO
   S3 (RustFS) → Persistente

3. REGISTRO
   MySQL → Metadata

4. CACHE
   Redis → URLs firmadas (24h TTL)

5. DISTRIBUCIÓN
   Reverb → AudioRequested event

6. REPRODUCCIÓN
   Vue → HTML5 Audio API
```

---

## Sistema de 3 Canales

### Canales Independientes

**Music (Música de fondo)**
- Volumen default: 0.6
- Loop continuo
- Cambios suaves con crossfade
- Adaptativo según fase del juego

**SFX (Efectos de sonido)**
- Volumen default: 0.8
- Play instantáneo
- Sin cola
- Por acción/evento

**Voice (Narraciones)**
- Volumen default: 1.0
- Cola secuencial (no superposición)
- Prioridad más alta
- Espera a que termine el audio anterior

### Organización en S3

```
audios/
├── music/
│   ├── lobby.mp3
│   ├── tension-low.mp3
│   ├── tension-medium.mp3
│   ├── tension-high.mp3
│   └── victory.mp3
│
├── sfx/
│   ├── ui/
│   │   ├── click.mp3
│   │   ├── transition.mp3
│   │   └── countdown-tick.mp3
│   ├── rope/
│   │   ├── tension-creak.mp3
│   │   └── rope-snap.mp3
│   ├── bomb/
│   │   ├── ticking.mp3
│   │   └── explosion.mp3
│   ├── roulette/
│   │   ├── spin.mp3
│   │   └── stop.mp3
│   └── results/
│       ├── victory.mp3
│       └── defeat.mp3
│
└── voices/
    ├── numbers/
    │   ├── 1.mp3
    │   └── ...50.mp3
    ├── system/
    │   ├── welcome.mp3
    │   ├── eliminated.mp3
    │   └── winner.mp3
    └── countdown/
        ├── 10.mp3
        └── ...1.mp3
```

---

## Tipos de Audio

### 1. Audios del Sistema (Reutilizables)

**Organizados por tipo y canal:**

#### Canal VOICE (Narraciones)
- **Números (1-50)**: "Jugador número X"
- **Diálogos generales**: Bienvenida, transiciones, eliminación, victoria
- **Por juego**:
  - Millonario: "Pregunta en pantalla", "Correcto", "Incorrecto"
  - Cuerda: "Prepárense", "El grupo ha perdido"
  - Deletréalo: "Deletrea: [palabra]", "Correcto", "Tiempo agotado"
  - Ruleta: "Gira la ruleta", "+500", "Pierdes todo", "Tenemos ganador"
  - Word Search: "Encuentra las palabras", "Primera palabra encontrada", "¡Ganador!"
  - Flappy: "¡A volar!", "Nuevo récord"

#### Canal SFX (Efectos)
- **UI**: Click, transición, countdown tick
- **Rope**: Tensión, cuerda rompiéndose
- **Bomb**: Ticking, explosión
- **Roulette**: Spin, parada
- **Results**: Victoria, derrota

#### Canal MUSIC (Ambiente)
- **Lobby**: Música relajada de espera
- **Tension Low/Medium/High**: Según fase del juego
- **Victory**: Celebración al ganar

### 2. Audios de Jugadores (Deletréalo)

- Grabados por jugadores
- Enviados para validación
- Auditables
- NO reutilizables

### 3. Música Ambiente (Adaptativa)

La música cambia según el estado del juego:
- **LOBBY**: Música relajada
- **MILLIONAIRE/SPELL**: Tensión media
- **ROPE**: Tensión alta (crece con el juego)
- **ROULETTE**: Tensión muy alta
- **BONUS GAMES**: Tensión baja, música divertida
- **WINNER**: Música de victoria

El backend emite eventos `AudioRequested` con `channel: 'music'` cuando cambia la fase.

---

## Evento AudioRequested Expandido

El evento ahora incluye información del canal:

```php
event(new AudioRequested(
  showId: $show->id,
  url: $signedUrl,
  context: 'eliminated',
  channel: 'voice',        // 'music', 'sfx', 'voice'
  volume: 1.0,            // 0.0-1.0
  metadata: [
    'player_id' => $player->id,
    'preload' => false,
    'loop' => false,
  ]
));
```

### Broadcast Payload

```typescript
Event: AudioRequested

Payload: {
  url: string               // URL firmada de S3
  context: string           // 'eliminated', 'click', 'tension-high'
  channel: 'music' | 'sfx' | 'voice'
  volume: number            // 0.0-1.0
  metadata?: {
    player_id?: number
    preload?: boolean       // Precargar sin reproducir
    loop?: boolean          // Loop continuo (música)
    fade?: {                // Crossfade
      in: number,           // Fade in duration (ms)
      out: number           // Fade out duration (ms)
    }
  }
}
```

---

## Preloading de Assets Críticos

Durante la fase LOBBY, el backend broadcast a todos los assets críticos para precarga:

```php
// En LobbyPhase
$criticalAssets = [
  ['key' => 'sfx/ui/click.mp3', 'channel' => 'sfx'],
  ['key' => 'sfx/ui/countdown-tick.mp3', 'channel' => 'sfx'],
  ['key' => 'voices/system/eliminated.mp3', 'channel' => 'voice'],
  ['key' => 'voices/system/welcome.mp3', 'channel' => 'voice'],
  ['key' => 'sfx/bomb/explosion.mp3', 'channel' => 'sfx'],
];

foreach ($criticalAssets as $asset) {
  $url = Storage::disk('rustfs')->url($asset['key']);
  event(new AudioRequested(
    showId: $show->id,
    url: $url,
    context: 'preload',
    channel: $asset['channel'],
    volume: 0,
    metadata: ['preload' => true]
  ));
}
```

---

## Cola Secuencial para Voice

Las narraciones en el canal Voice se reproducen secuencialmente para evitar superposición:

**Backend NO maneja la cola**, solo emite eventos en orden. **Frontend** (Vue) gestiona la cola:

```javascript
// Frontend - audioService.ts
class AudioService {
  private voiceQueue: Array<{url: string, context: string}> = []
  private isPlayingVoice = false
  
  playVoice(url: string, context: string) {
    this.voiceQueue.push({url, context})
    if (!this.isPlayingVoice) {
      this.processVoiceQueue()
    }
  }
  
  async processVoiceQueue() {
    if (this.voiceQueue.length === 0) {
      this.isPlayingVoice = false
      return
    }
    
    this.isPlayingVoice = true
    const {url, context} = this.voiceQueue.shift()!
    
    const audio = new Audio(url)
    audio.volume = 1.0
    audio.onended = () => this.processVoiceQueue()
    await audio.play()
  }
}
```

Backend solo se asegura de emitir eventos de voice en orden lógico.

---

## Patrones de Integración

### 1. Juego con tensión creciente (La Cuerda)

```php
// Backend emite música + SFX sync
event(new AudioRequested(
  showId: $show->id,
  url: $tensionHighMusicUrl,
  context: 'rope-tension-high',
  channel: 'music',
  volume: 0.6,
  metadata: ['loop' => true, 'fade' => ['in' => 1000]]
));

// Cada X segundos, SFX de tensión
event(new AudioRequested(
  showId: $show->id,
  url: $creakSfxUrl,
  context: 'rope-creak',
  channel: 'sfx',
  volume: 0.8
));
```

### 2. Eliminación con narración

```php
// 1. SFX de eliminación (inmediato)
event(new AudioRequested(
  showId: $show->id,
  url: $defeatSfxUrl,
  context: 'defeat-sfx',
  channel: 'sfx',
  volume: 0.8
));

// 2. Narración (cola voice)
event(new AudioRequested(
  showId: $show->id,
  url: $eliminatedVoiceUrl,
  context: 'eliminated',
  channel: 'voice',
  volume: 1.0,
  metadata: ['player_id' => $player->id]
));
```

### 3. Countdown con ticks

```php
// Music background
event(new AudioRequested(..., channel: 'music', loop: true));

// Cada segundo, tick SFX
for ($i = 10; $i >= 1; $i--) {
  sleep(1);
  event(new AudioRequested(
    ...,
    url: $tickSfxUrl,
    context: 'countdown-tick',
    channel: 'sfx'
  ));
}
```

### 4. Bonus game activado

```php
// Cambiar a música divertida
event(new AudioRequested(
  ...,
  url: $bonusMusicUrl,
  channel: 'music',
  metadata: ['loop' => true, 'fade' => ['in' => 2000, 'out' => 1000]]
));

// Narración de inicio
event(new AudioRequested(
  ...,
  url: $bonusStartVoiceUrl,
  context: 'bonus-start',
  channel: 'voice'
));
```

### 5. Three.js sync (La Cuerda visual)

Frontend puede sincronizar visual 3D con audio usando AudioContext:

```typescript
// Frontend solo
const audioCtx = new AudioContext()
const analyser = audioCtx.createAnalyser()
// Sync rope tension visual with music amplitude
```

Backend no necesita cambios para esto.

### 6. Transiciones de fase

```php
// Al cambiar ShowPhase
event(new ShowStateChanged($show, $newPhase));

// Cambiar música según fase
$musicMap = [
  ShowPhase::LOBBY => 'lobby.mp3',
  ShowPhase::MILLIONAIRE => 'tension-medium.mp3',
  ShowPhase::ROPE => 'tension-high.mp3',
  ShowPhase::BONUS_WORD_SEARCH => 'bonus-fun.mp3',
  ShowPhase::ROULETTE => 'tension-extreme.mp3',
  ShowPhase::WINNER => 'victory.mp3',
];

$musicFile = $musicMap[$newPhase] ?? 'tension-low.mp3';
event(new AudioRequested(
  ...,
  url: Storage::url("audios/music/{$musicFile}"),
  channel: 'music',
  metadata: ['loop' => true, 'fade' => ['in' => 2000, 'out' => 2000]]
));
```

### 7. Click feedback universal

Todos los clicks de UI reproducen SFX de click. Backend NO emite esto, solo frontend local:

```typescript
// Frontend - global click handler
onClick() {
  audioService.playSFX('/audios/sfx/ui/click.mp3')
  // ... rest of click logic
}
```

---

## Seeders Expandidos

Los seeders ahora organizan audios por tipo:

```php
class SystemAudioSeeder extends Seeder {
  public function run() {
    // VOICE audios
    $voiceDialogs = [
      'eliminated' => ['Has sido eliminado', 'Jugador eliminado'],
      'passed' => ['Avanzas a la siguiente ronda'],
      'intro' => ['Bienvenidos al juego'],
      // ...
    ];
    
    foreach ($voiceDialogs as $context => $texts) {
      foreach ($texts as $text) {
        $audio = TopMediai::generate($text);
        SystemAudio::create([
          'context' => $context,
          'audio_type' => 'voice',
          'default_volume' => 1.0,
          'text' => $text,
          's3_key' => $audio['key'],
        ]);
      }
    }
    
    // SFX audios (sin generar, assets pre-existentes)
    $sfxAssets = [
      ['context' => 'click', 's3_key' => 'sfx/ui/click.mp3'],
      ['context' => 'explosion', 's3_key' => 'sfx/bomb/explosion.mp3'],
      // ...
    ];
    
    foreach ($sfxAssets as $asset) {
      SystemAudio::create([
        ...$asset,
        'audio_type' => 'sfx',
        'default_volume' => 0.8,
        'text' => '',
        'reusable' => true,
      ]);
    }
    
    // MUSIC audios
    $musicAssets = [
      ['context' => 'lobby-music', 's3_key' => 'music/lobby.mp3'],
      ['context' => 'tension-high', 's3_key' => 'music/tension-high.mp3'],
      // ...
    ];
    
    foreach ($musicAssets as $asset) {
      SystemAudio::create([
        ...$asset,
        'audio_type' => 'music',
        'default_volume' => 0.6,
        'text' => '',
        'reusable' => true,
      ]);
    }
  }
}
```

---

## Volúmenes por Canal

### Defaults

- **Music**: 0.6 (background, no debe opacar voice)
- **SFX**: 0.8 (feedback claro pero no abrumador)
- **Voice**: 1.0 (prioridad máxima, narración clara)

### Ajustables

El supervisor puede ajustar volúmenes globales via dashboard:

```php
POST /api/supervisor/audio/volume
Body: {
  channel: 'music' | 'sfx' | 'voice',
  volume: 0.0-1.0
}

// Broadcast a todos
event(new AudioVolumeChanged($channel, $volume));
```

Frontend aplica el ajuste globalmente a su canal respectivo.

---

## Tabla `system_audios`

```sql
CREATE TABLE system_audios (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  context VARCHAR(50) NOT NULL,        -- 'eliminated', 'passed', 'intro', etc.
  audio_type ENUM('music', 'sfx', 'voice') DEFAULT 'voice',
  default_volume DECIMAL(3,2) DEFAULT 1.0,
  text TEXT NOT NULL,                  -- Texto generado
  s3_key VARCHAR(255) NOT NULL,        -- Ruta en S3
  locale VARCHAR(10) DEFAULT 'es-CO',  -- Español colombiano
  reusable BOOLEAN DEFAULT true,       -- Es reutilizable
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  INDEX idx_context (context),
  INDEX idx_audio_type (audio_type)
);
```

**Nuevos campos:**
- `audio_type`: Canal de reproducción (music/sfx/voice)
- `default_volume`: Volumen default 0.0-1.0

## Tabla `number_audios`

```sql
CREATE TABLE number_audios (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  number TINYINT UNSIGNED UNIQUE NOT NULL,  -- 1..50
  s3_key VARCHAR(255) NOT NULL,
  locale VARCHAR(10) DEFAULT 'es-CO',
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

## Tabla `spell_audios`

```sql
CREATE TABLE spell_audios (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  player_id BIGINT UNSIGNED NOT NULL,
  word VARCHAR(100) NOT NULL,
  s3_key VARCHAR(255) NOT NULL,
  status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  supervisor_id BIGINT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  INDEX idx_status (status),
  INDEX idx_player (player_id),
  FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
  FOREIGN KEY (supervisor_id) REFERENCES players(id) ON DELETE SET NULL
);
```

---

## Servicios de Audio

### TopMediaiService

```php
class TopMediaiService {
  protected string $apiKey;
  protected string $endpoint = 'https://api.topmediai.com/v1/text2speech';
  
  public function generate(string $text, string $speaker = 'default'): array
  {
    $response = Http::withHeaders([
      'x-api-key' => $this->apiKey,
      'Content-Type' => 'application/json',
    ])->post($this->endpoint, [
      'text' => $text,
      'speaker' => $speaker,
      'emotion' => 'Neutral',
    ]);
    
    return [
      'binary' => $response->body(),
      'key' => 'audios/'.Str::uuid().'.mp3',
    ];
  }
}
```

### ElevenLabsService (Fallback)

```php
class ElevenLabsService {
  public function generate(string $text): array
  {
    // Similar a TopMediai
  }
}
```

### AudioStorageService

```php
class AudioStorageService {
  public function store(string $key, string $binary): string
  {
    Storage::disk('rustfs')->put($key, $binary);
    return Storage::disk('rustfs')->url($key);
  }
  
  public function getSignedUrl(string $key, int $ttl = 86400): string
  {
    return Storage::disk('rustfs')->temporaryUrl($key, now()->addSeconds($ttl));
  }
}
```

### AudioDispatcher

```php
class AudioDispatcher {
  public function play(string $context, ?array $vars = []): void
  {
    // Buscar en cache
    $url = Cache::remember("audio:{$context}", 3600, function () use ($context) {
      $audio = SystemAudio::where('context', $context)->inRandomOrder()->first();
      return $this->storage->getSignedUrl($audio->s3_key);
    });
    
    // Broadcast
    event(new AudioRequested($url, $context));
  }
}
```

---

## Seeders

### SystemAudioSeeder

```php
class SystemAudioSeeder extends Seeder {
  public function run() {
    $dialogs = [
      'eliminated' => [
        'Has sido eliminado.',
        'Jugador eliminado.',
        'Hasta aquí llegaste.',
      ],
      'passed' => [
        'Avanzas a la siguiente ronda.',
        'Sigues con vida.',
      ],
      'intro' => [
        'Bienvenidos al juego.',
      ],
    ];
    
    foreach ($dialogs as $context => $texts) {
      foreach ($texts as $text) {
        $audio = app(TopMediaiService::class)->generate($text);
        app(AudioStorageService::class)->store($audio['key'], $audio['binary']);
        
        SystemAudio::create([
          'context' => $context,
          'text' => $text,
          's3_key' => $audio['key'],
        ]);
      }
    }
  }
}
```

### NumberAudioSeeder

```php
class NumberAudioSeeder extends Seeder {
  public function run() {
    for ($i = 1; $i <= 50; $i++) {
      $text = "Jugador número {$i}";
      $audio = app(TopMediaiService::class)->generate($text);
      app(AudioStorageService::class)->store($audio['key'], $audio['binary']);
      
      NumberAudio::create([
        'number' => $i,
        's3_key' => $audio['key'],
      ]);
    }
  }
}
```

**Ejecutar una vez**:
```bash
php artisan db:seed --class=SystemAudioSeeder
php artisan db:seed --class=NumberAudioSeeder
```

---

## Flujo: Audio del Sistema

```
1. TRIGGER
   event(new PlayerEliminated($player))

2. LISTENER
   PlayEliminationSound::handle()
   
3. DISPATCHER
   AudioDispatcher::play('eliminated')
   
4. CACHE CHECK
   Redis → "audio:eliminated" → URL?
   
5. IF MISS
   DB → SystemAudio WHERE context='eliminated'
   S3 → Get signed URL (24h TTL)
   Redis → Cache URL
   
6. BROADCAST
   AudioRequested($url, 'eliminated')
   
7. CLIENTE
   Echo.listen('AudioRequested', (e) => {
     audioService.play(e.url)
   })
```

---

## Flujo: Audio de Jugador (Deletréalo)

```
1. JUGADOR GRABA
   Vue → MediaRecorder API → Blob

2. ENVÍA
   POST /api/spell/submit
   FormData: audio, word, player_id

3. BACKEND VALIDA
   - Formato: audio/webm, audio/mp3
   - Tamaño: < 5MB
   - Duración: < 30s

4. ALMACENA
   S3 → audios/spell/{uuid}.mp3
   DB → spell_audios (status: pending)

5. NOTIFICA SUPERVISORES
   event(new SpellAudioSubmitted($submission))
   Canal: 'supervisor.show'

6. SUPERVISOR VALIDA
   POST /api/spell/validate
   { id, result: 'approved' | 'rejected' }

7. RESULTADO
   event(new SpellValidated($player, $result))
   
8. SI RECHAZADO
   Action → EliminatePlayer
   event(new PlayerEliminated($player))
```

---

## Configuración

### `.env`
```env
# TopMediai (Principal)
TOPMEDIAI_API_KEY=your_key_here

# ElevenLabs (Fallback)
ELEVENLABS_API_KEY=your_backup_key

# RustFS (S3)
RUSTFS_KEY=your_s3_key
RUSTFS_SECRET=your_s3_secret
RUSTFS_REGION=us-east-1
RUSTFS_BUCKET=ruleta-audios
RUSTFS_ENDPOINT=https://s3.amazonaws.com
```

### `config/services.php`
```php
'topmediai' => [
    'key' => env('TOPMEDIAI_API_KEY'),
],

'elevenlabs' => [
    'key' => env('ELEVENLABS_API_KEY'),
],
```

---

## Audio Tracking Events

El backend emite eventos WebSocket para que el frontend (`MusicBox.vue`) pueda trackear qué está sonando en tiempo real.

### TrackStarted

Emitido cuando un track de audio comienza a reproducirse (principalmente canal `music` y `voice`).

```php
// Backend Service
broadcast(new TrackStarted(
  showId: $show->id,
  trackId: 'millionaire_theme',
  channel: 'music',
  url: $signedUrl,
  durationSeconds: 120,
  volume: 0.6,
  metadata: [
    'game' => 'millionaire',
    'loop' => true,
  ]
))->toOthers();
```

**WebSocket Payload**:
```json
{
  "track_id": "millionaire_theme",
  "channel": "music",
  "url": "https://s3.../millionaire_theme.mp3",
  "duration_seconds": 120,
  "volume": 0.6,
  "metadata": {
    "game": "millionaire",
    "loop": true
  }
}
```

**Frontend**: `MusicBox.vue` (upper-left UI) actualiza:
- Track name display
- Waveform visualizer
- Progress bar

### TrackEnded

Emitido cuando un track termina (completado, detenido manualmente, o error).

```php
broadcast(new TrackEnded(
  showId: $show->id,
  trackId: 'millionaire_theme',
  channel: 'music',
  reason: 'completed',  // 'completed' | 'stopped' | 'error'
  metadata: []
))->toOthers();
```

**WebSocket Payload**:
```json
{
  "track_id": "millionaire_theme",
  "channel": "music",
  "reason": "completed"
}
```

**Frontend**: `MusicBox.vue` limpia UI, detiene visualizer.

### VolumeChanged

Emitido cuando el supervisor cambia el volumen global de un canal (desde el panel de control).

```php
// Endpoint: POST /api/supervisor/audio/volume
broadcast(new VolumeChanged(
  showId: $show->id,
  channel: 'music',  // 'music' | 'sfx' | 'voice'
  volume: 0.4,       // 0.0 - 1.0
  previousVolume: 0.6
))->toOthers();
```

**WebSocket Payload**:
```json
{
  "channel": "music",
  "volume": 0.4,
  "previous_volume": 0.6
}
```

**Frontend**: `MusicBox.vue` actualiza sliders, aplica nuevo volumen a HTML5 Audio instances.

### Integración MusicBox UI

El componente `MusicBox.vue` (esquina superior izquierda del juego) escucha estos eventos:

```typescript
// Frontend - MusicBox.vue
Echo.channel(`game.show.${showId}`)
  .listen('.TrackStarted', (event: TrackStartedEvent) => {
    musicBox.value.currentTrack = event.track_id
    musicBox.value.channel = event.channel
    musicBox.value.duration = event.duration_seconds
    startVisualizer(event.url)
  })
  .listen('.TrackEnded', (event: TrackEndedEvent) => {
    musicBox.value.currentTrack = null
    stopVisualizer()
  })
  .listen('.VolumeChanged', (event: VolumeChangedEvent) => {
    audioService.setChannelVolume(event.channel, event.volume)
    musicBox.value.volumes[event.channel] = event.volume
  })
```

**Visualización**:
- Track name (e.g. "Millonario - Tensión Media")
- Waveform animado (AudioContext analyser)
- Time progress (0:45 / 2:00)
- Channel indicator (🎵 Music | 🔊 SFX | 🎙️ Voice)
- Volume slider per channel (solo supervisor)

### Metadata Tracking

El backend también guarda metadata de reproducción en la tabla `audio_plays`:

```sql
CREATE TABLE audio_plays (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  show_id BIGINT NOT NULL,
  audio_id BIGINT NULL,  -- Si es SystemAudio
  track_id VARCHAR(100) NOT NULL,
  channel ENUM('music', 'sfx', 'voice') NOT NULL,
  url TEXT NOT NULL,
  played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  duration_seconds INT NULL,
  completed BOOLEAN DEFAULT FALSE,
  
  INDEX (show_id),
  INDEX (channel),
  INDEX (played_at)
);
```

```php
// Al emitir TrackStarted
AudioPlay::create([
  'show_id' => $show->id,
  'audio_id' => $audio?->id,
  'track_id' => $trackId,
  'channel' => $channel,
  'url' => $url,
  'duration_seconds' => $duration,
  'played_at' => now(),
]);

// Al emitir TrackEnded
AudioPlay::where('track_id', $trackId)
  ->where('show_id', $show->id)
  ->latest()
  ->first()
  ?->update(['completed' => $reason === 'completed']);
```

**Uso:**
- Analytics de qué audios se reproducen más
- Debugging de audio system
- Auditoría de supervisores cambiando volumen

---

## Optimizaciones

### Precarga (Lobby)
```javascript
// Vue - Durante LOBBY
const preload = [
  '/audios/numbers/1.mp3',
  '/audios/system/intro.mp3',
  '/audios/system/eliminated.mp3',
]

preload.forEach(url => {
  const audio = new Audio(url)
  audio.preload = 'auto'
})
```

### Compresión
- Formato: MP3 44.1kHz 128kbps
- Tamaño típico: ~50KB por audio de 5s

### TTL de URLs Firmadas
- Sistema: 24h (largo, son reutilizables)
- Jugadores: 1h (corto, son únicos)

---

## Monitoreo

### Métricas
```php
// Audios generados hoy
SystemAudio::whereDate('created_at', today())->count();

// Audios pendientes de validación
SpellAudio::where('status', 'pending')->count();

// Uso de S3
Storage::disk('rustfs')->size('audios/');
```

### Logs
```php
Log::channel('audio')->info('Audio generated', [
  'context' => 'eliminated',
  's3_key' => $audio->s3_key,
  'cost' => $cost, // TopMediai cobra por caracter
]);
```

---

## Troubleshooting

### Audio no se reproduce
1. Verificar URL firmada no expiró
2. Verificar CORS en S3
3. Verificar formato soportado por navegador

### TopMediai falla
1. Cambiar a ElevenLabs automáticamente
2. Logs en `storage/logs/audio.log`

### Latencia alta
1. Verificar que audios estén en cache
2. Usar CDN frente a S3 (Cloudflare R2)

---

**Ver también**:
- `backend-structure.md` - Ubicación de servicios
- `database-schema.md` - Tablas completas
- `rules/game-implementation.md` - Uso de audio en juegos
