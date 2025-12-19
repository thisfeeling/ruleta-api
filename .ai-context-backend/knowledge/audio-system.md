# Audio System - Ruleta Familiar

## Visión General

El sistema de audio es crítico para la experiencia inmersiva del game show. Combina:
- **Voz del narrador** (estilo SquidCraft)
- **Audios del sistema** (números, diálogos)
- **Audios de jugadores** (Deletréalo)
- **Música ambiente** (adaptativa)

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

## Tipos de Audio

### 1. Audios del Sistema (Reutilizables)

**Números (1-50)**
```
"Jugador número 1"
"Jugador número 2"
...
"Jugador número 50"
```

**Diálogos Generales**
- Bienvenida
- Transiciones
- Eliminación
- Victoria

**Por Juego**
- Millonario: "Pregunta en pantalla", "Correcto", "Incorrecto"
- Cuerda: "Prepárense", "El grupo ha perdido"
- Deletréalo: "Deletrea: [palabra]", "Correcto", "Tiempo agotado"
- Ruleta: "Gira la ruleta", "+500", "Pierdes todo", "Tenemos ganador"

### 2. Audios de Jugadores (Deletréalo)

- Grabados por jugadores
- Enviados para validación
- Auditables
- NO reutilizables

### 3. Música Ambiente (Futuro)

- Tensión baja / media / alta
- Sincronizada con estado del juego

---

## Tabla `system_audios`

```sql
CREATE TABLE system_audios (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  context VARCHAR(50) NOT NULL,        -- 'eliminated', 'passed', 'intro', etc.
  text TEXT NOT NULL,                  -- Texto generado
  s3_key VARCHAR(255) NOT NULL,        -- Ruta en S3
  locale VARCHAR(10) DEFAULT 'es-CO',  -- Español colombiano
  reusable BOOLEAN DEFAULT true,       -- Es reutilizable
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  INDEX idx_context (context)
);
```

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
