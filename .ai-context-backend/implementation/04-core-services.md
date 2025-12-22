# 04 - Core Services

**Status**: [x] Completed

## Objetivo

Implementar servicios fundamentales: Storage S3, TTS (ElevenLabs), Audio Management, y helpers reutilizables.

## Dependencias

- **Anterior**: 03 - Models & Relationships
- **Sincronización Frontend**: URLs de audio deben ser accesibles desde frontend

## Servicios a Implementar

1. **StorageService** - Gestión de S3 (RustFS)
2. **TTSService** - Text-to-Speech con ElevenLabs
3. **AudioService** - Gestión de AudioTracks
4. **PINGenerator** - Generación de PINs únicos

## Implementación

### 4.1 Storage Service

```bash
mkdir -p app/Services/Storage
touch app/Services/Storage/StorageService.php
```

```php
<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
    protected string $disk = 's3';
    
    /**
     * Upload file to S3
     */
    public function upload(string $path, $content, array $options = []): array
    {
        $fullPath = $this->normalizePath($path);
        
        Storage::disk($this->disk)->put($fullPath, $content, array_merge([
            'visibility' => 'private',
            'ContentType' => $this->guessContentType($fullPath),
        ], $options));
        
        return [
            'path' => $fullPath,
            'url' => $this->getUrl($fullPath),
            'signed_url' => $this->getSignedUrl($fullPath),
        ];
    }
    
    /**
     * Upload audio file
     */
    public function uploadAudio(string $filename, $content, string $subfolder = 'generated'): array
    {
        $path = "audio/{$subfolder}/" . Str::slug(pathinfo($filename, PATHINFO_FILENAME)) . '.mp3';
        return $this->upload($path, $content);
    }
    
    /**
     * Upload recording (spell game)
     */
    public function uploadRecording(int $showId, int $playerId, $content): array
    {
        $filename = "recordings/show-{$showId}/player-{$playerId}-" . now()->timestamp . ".mp3";
        return $this->upload($filename, $content);
    }
    
    /**
     * Upload audit backup
     */
    public function uploadAuditBackup(int $showId, string $date, array $logs): string
    {
        $filename = "audit/show-{$showId}/{$date}.json";
        $this->upload($filename, json_encode($logs));
        return $filename;
    }
    
    /**
     * Get public URL
     */
    public function getUrl(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }
    
    /**
     * Get signed (temporary) URL
     */
    public function getSignedUrl(string $path, int $expiresInMinutes = 60): string
    {
        return Storage::disk($this->disk)->temporaryUrl(
            $path,
            now()->addMinutes($expiresInMinutes)
        );
    }
    
    /**
     * Check if file exists
     */
    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }
    
    /**
     * Delete file
     */
    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }
    
    /**
     * Get file size in bytes
     */
    public function size(string $path): int
    {
        return Storage::disk($this->disk)->size($path);
    }
    
    /**
     * Download file content
     */
    public function get(string $path): string
    {
        return Storage::disk($this->disk)->get($path);
    }
    
    // Helpers
    protected function normalizePath(string $path): string
    {
        return ltrim($path, '/');
    }
    
    protected function guessContentType(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        
        return match($extension) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'json' => 'application/json',
            'txt' => 'text/plain',
            default => 'application/octet-stream',
        };
    }
}
```

### 4.2 TTS Service (ElevenLabs)

```bash
mkdir -p app/Services/TTS
touch app/Services/TTS/TTSService.php
```

```php
<?php

namespace App\Services\TTS;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\Storage\StorageService;

class TTSService
{
    protected string $apiKey;
    protected string $voiceId;
    protected StorageService $storage;
    
    public function __construct(StorageService $storage)
    {
        $this->apiKey = config('services.elevenlabs.api_key');
        $this->voiceId = config('services.elevenlabs.voice_id');
        $this->storage = $storage;
    }
    
    /**
     * Generate speech from text
     */
    public function generate(string $text, ?string $voiceId = null, array $options = []): array
    {
        $voiceId = $voiceId ?? $this->voiceId;
        
        Log::info('Generating TTS', ['text' => $text, 'voice_id' => $voiceId]);
        
        $response = Http::withHeaders([
            'xi-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", array_merge([
            'text' => $text,
            'model_id' => 'eleven_multilingual_v2',
            'voice_settings' => [
                'stability' => 0.5,
                'similarity_boost' => 0.75,
            ],
        ], $options));
        
        if (!$response->successful()) {
            Log::error('TTS generation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('TTS generation failed: ' . $response->body());
        }
        
        $audioContent = $response->body();
        $filename = $this->generateFilename($text);
        
        return $this->storage->uploadAudio($filename, $audioContent);
    }
    
    /**
     * Generate and save to database
     */
    public function generateAndStore(string $key, string $text, string $channel = 'voice'): \App\Models\AudioTrack
    {
        // Check if already exists
        $existing = \App\Models\AudioTrack::where('key', $key)->first();
        if ($existing) {
            Log::info('Audio track already exists', ['key' => $key]);
            return $existing;
        }
        
        // Generate new
        $result = $this->generate($text);
        
        // Get duration (optional, requires audio analysis library)
        $duration = $this->estimateDuration($text);
        
        // Create AudioTrack
        return \App\Models\AudioTrack::create([
            'key' => $key,
            'channel' => $channel,
            's3_path' => $result['path'],
            's3_url' => $result['url'],
            'duration_ms' => $duration,
            'default_volume' => $this->getDefaultVolume($channel),
            'metadata' => [
                'text' => $text,
                'generated_at' => now()->toIso8601String(),
            ],
            'is_preloaded' => $this->shouldPreload($key),
        ]);
    }
    
    /**
     * Generate number audio (1-50)
     */
    public function generateNumbers(int $from = 1, int $to = 50): array
    {
        $tracks = [];
        
        for ($i = $from; $i <= $to; $i++) {
            $key = "narrator.number_{$i}";
            $text = "Jugador número {$i}";
            
            $tracks[] = $this->generateAndStore($key, $text, 'voice');
        }
        
        return $tracks;
    }
    
    // Helpers
    protected function generateFilename(string $text): string
    {
        $slug = \Str::slug(substr($text, 0, 50));
        $hash = substr(md5($text), 0, 8);
        return "{$slug}-{$hash}.mp3";
    }
    
    protected function estimateDuration(string $text): int
    {
        // Rough estimate: 150 words per minute
        $words = str_word_count($text);
        return (int) (($words / 150) * 60 * 1000);
    }
    
    protected function getDefaultVolume(string $channel): float
    {
        return match($channel) {
            'music' => 0.6,
            'sfx' => 0.8,
            'voice' => 1.0,
            default => 1.0,
        };
    }
    
    protected function shouldPreload(string $key): bool
    {
        // Preload critical audio
        $preloadKeys = [
            'narrator.welcome',
            'narrator.game_start',
            'sfx.click',
            'sfx.correct',
            'sfx.wrong',
            'sfx.eliminated',
        ];
        
        return in_array($key, $preloadKeys);
    }
}
```

Agregar configuración en `config/services.php`:

```php
'elevenlabs' => [
    'api_key' => env('ELEVENLABS_API_KEY'),
    'voice_id' => env('ELEVENLABS_VOICE_ID'),
],
**With Timestamps & Multi-Context**

The TTS service also supports timestamped generation using the `/with-timestamps` endpoint and streaming/multi-context websocket flows when character-level timing is required (useful for the "Deletréalo" game). Use `TTSService::convertWithTimestamps` to retrieve `audio_base64` plus `alignment` and `normalized_alignment`. Use `TTSService::generateWithTimestampsAndStore` to generate, upload, and save the alignment data to `AudioTrack.metadata`.

**Audit / Cost Tracking**

Each TTS generation stores response headers returned by ElevenLabs (e.g. `x-character-count`, `request-id`) in `AudioTrack.metadata.elevenlabs`. We also record per-generation usage in a `tts_usages` table to allow daily/weekly aggregation for cost tracking. The model is `App\Models\TTSUsage` and the table is `tts_usages`.```

### 4.3 Audio Service

```bash
mkdir -p app/Services/Audio
touch app/Services/Audio/AudioService.php
```

```php
<?php

namespace App\Services\Audio;

use App\Models\AudioTrack;
use App\Services\Storage\StorageService;
use Illuminate\Support\Collection;

class AudioService
{
    protected StorageService $storage;
    
    public function __construct(StorageService $storage)
    {
        $this->storage = $storage;
    }
    
    /**
     * Get track by key
     */
    public function getTrack(string $key): ?AudioTrack
    {
        return AudioTrack::where('key', $key)->first();
    }
    
    /**
     * Get tracks by channel
     */
    public function getChannelTracks(string $channel): Collection
    {
        return AudioTrack::channel($channel)->get();
    }
    
    /**
     * Get preloadable tracks
     */
    public function getPreloadTracks(): Collection
    {
        return AudioTrack::preloaded()->get();
    }
    
    /**
     * Get signed URLs for tracks
     */
    public function getSignedUrls(array $keys, int $expiresInMinutes = 60): array
    {
        $tracks = AudioTrack::whereIn('key', $keys)->get();
        
        return $tracks->mapWithKeys(function (AudioTrack $track) use ($expiresInMinutes) {
            return [$track->key => $track->getSignedUrl($expiresInMinutes)];
        })->toArray();
    }
    
    /**
     * Create track from S3 file
     */
    public function createTrack(
        string $key,
        string $channel,
        string $s3Path,
        ?int $durationMs = null,
        bool $isPreloaded = false
    ): AudioTrack {
        return AudioTrack::create([
            'key' => $key,
            'channel' => $channel,
            's3_path' => $s3Path,
            's3_url' => $this->storage->getUrl($s3Path),
            'duration_ms' => $durationMs,
            'default_volume' => $this->getDefaultVolume($channel),
            'is_preloaded' => $isPreloaded,
        ]);
    }
    
    /**
     * Record play event
     */
    public function recordPlay(
        string $key,
        ?int $showId = null,
        ?int $userId = null,
        ?string $context = null
    ): void {
        $track = $this->getTrack($key);
        
        if ($track) {
            $track->recordPlay($showId, $userId, $context);
        }
    }
    
    /**
     * Get play statistics
     */
    public function getPlayStats(string $key, int $days = 7): array
    {
        $track = $this->getTrack($key);
        
        if (!$track) {
            return [];
        }
        
        $plays = $track->plays()
            ->where('played_at', '>', now()->subDays($days))
            ->get();
        
        return [
            'total_plays' => $plays->count(),
            'unique_shows' => $plays->pluck('show_id')->unique()->count(),
            'unique_users' => $plays->pluck('played_by')->unique()->count(),
            'by_context' => $plays->groupBy('context')->map->count()->toArray(),
        ];
    }
    
    // Helpers
    protected function getDefaultVolume(string $channel): float
    {
        return match($channel) {
            'music' => 0.6,
            'sfx' => 0.8,
            'voice' => 1.0,
            default => 1.0,
        };
    }
}
```

### 4.4 PIN Generator Service

```bash
mkdir -p app/Services/Player
touch app/Services/Player/PINGeneratorService.php
```

```php
<?php

namespace App\Services\Player;

use App\Models\Player;
use Illuminate\Support\Facades\DB;

class PINGeneratorService
{
    /**
     * Generate unique 4-digit PIN
     */
    public function generate(): string
    {
        $attempts = 0;
        $maxAttempts = 100;
        
        do {
            $pin = $this->generateRandom();
            $exists = Player::where('pin', $pin)->exists();
            $attempts++;
            
            if ($attempts >= $maxAttempts) {
                throw new \Exception('Unable to generate unique PIN after ' . $maxAttempts . ' attempts');
            }
        } while ($exists);
        
        return $pin;
    }
    
    /**
     * Generate multiple unique PINs
     */
    public function generateBatch(int $count): array
    {
        $pins = [];
        
        for ($i = 0; $i < $count; $i++) {
            $pins[] = $this->generate();
        }
        
        return $pins;
    }
    
    /**
     * Validate PIN format
     */
    public function validate(string $pin): bool
    {
        return preg_match('/^\d{4}$/', $pin) === 1;
    }
    
    /**
     * Find player by PIN
     */
    public function findPlayer(string $pin): ?Player
    {
        if (!$this->validate($pin)) {
            return null;
        }
        
        return Player::where('pin', $pin)
            ->whereIn('status', ['active', 'disconnected'])
            ->first();
    }
    
    // Helpers
    protected function generateRandom(): string
    {
        return str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    }
}
```

### 4.5 Service Provider

Registrar servicios en `app/Providers/AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Storage\StorageService;
use App\Services\TTS\TTSService;
use App\Services\Audio\AudioService;
use App\Services\Player\PINGeneratorService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StorageService::class);
        $this->app->singleton(TTSService::class);
        $this->app->singleton(AudioService::class);
        $this->app->singleton(PINGeneratorService::class);
    }

    public function boot(): void
    {
        //
    }
}
```

### 4.6 Artisan Commands

Crear comando para generar audios base:

```bash
php artisan make:command GenerateBaseAudios
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TTS\TTSService;

class GenerateBaseAudios extends Command
{
    protected $signature = 'audio:generate-base';
    protected $description = 'Generate base audio files (numbers, dialogs)';

    public function handle(TTSService $tts): int
    {
        $this->info('Generating numbers 1-50...');
        $tts->generateNumbers(1, 50);
        
        $this->info('Generating common dialogs...');
        
        $dialogs = [
            'narrator.welcome' => '¡Bienvenidos a Ruleta Familiar!',
            'narrator.game_start' => 'El juego está por comenzar',
            'narrator.eliminated' => 'Has sido eliminado',
            'narrator.winner' => '¡Felicitaciones! Eres el ganador',
        ];
        
        foreach ($dialogs as $key => $text) {
            $this->info("Generating: {$key}");
            $tts->generateAndStore($key, $text, 'voice');
        }
        
        $this->info('✅ Base audios generated successfully!');
        
        return Command::SUCCESS;
    }
}
```

## Verificación

```bash
# Test S3 connection
php artisan tinker
>>> app(StorageService::class)->upload('test.txt', 'Hello World');

# Generate test TTS
>>> app(TTSService::class)->generate('Hola mundo');

# Generate base audios
php artisan audio:generate-base

# Test PIN generation
>>> app(PINGeneratorService::class)->generate();
```

## Sincronización con Frontend

1. **Audio URLs**: El frontend recibirá signed URLs via WebSocket
2. **PIN System**: El frontend envía PIN de 4 dígitos para reconexión
3. **Storage**: Todos los archivos están en S3, no en public/

## Próximos Pasos

→ **05 - Reverb WebSocket Setup**: Configurar canales y eventos
