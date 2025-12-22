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

        // Add timeout and retry for resiliency
        $response = Http::withHeaders([
            'xi-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(20)->retry(3, 100)->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", array_merge([
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

        // Capture ElevenLabs headers for audit/cost tracking
        $elevenlabsMeta = [
            'character_count' => $response->header('x-character-count') ?? $response->header('X-Character-Count'),
            'request_id' => $response->header('request-id') ?? $response->header('Request-Id'),
            'status_code' => $response->status(),
        ];

        Log::info('TTS generated', array_merge(['filename' => $filename], $elevenlabsMeta));

        $uploadResult = $this->storage->uploadAudio($filename, $audioContent);
        $uploadResult['elevenlabs'] = $elevenlabsMeta;

        return $uploadResult;
    }

    /**
     * Generate speech with timestamps
     */
    public function convertWithTimestamps(string $text, ?string $voiceId = null, array $options = []): array
    {
        $voiceId = $voiceId ?? $this->voiceId;

        Log::info('Generating TTS with timestamps', ['text' => $text, 'voice_id' => $voiceId]);

        $response = Http::withHeaders([
            'xi-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30)->retry(3, 100)->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}/with-timestamps", array_merge([
            'text' => $text,
            'model_id' => 'eleven_multilingual_v2',
        ], $options));

        if (!$response->successful()) {
            Log::error('TTS with timestamps failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('TTS with timestamps failed: ' . $response->body());
        }

        $body = $response->json();

        // The API returns base64 audio under 'audio_base64' when using with-timestamps
        $audioBase64 = $body['audio_base64'] ?? null;
        $alignment = $body['alignment'] ?? null;
        $normalized = $body['normalized_alignment'] ?? null;

        if (!$audioBase64) {
            throw new \Exception('TTS response missing audio_base64');
        }

        $audioContent = base64_decode($audioBase64);
        $filename = $this->generateFilename($text);

        $elevenlabsMeta = [
            'character_count' => $response->header('x-character-count') ?? $response->header('X-Character-Count'),
            'request_id' => $response->header('request-id') ?? $response->header('Request-Id'),
            'status_code' => $response->status(),
        ];

        $uploadResult = $this->storage->uploadAudio($filename, $audioContent);
        $uploadResult['elevenlabs'] = $elevenlabsMeta;
        $uploadResult['alignment'] = $alignment;
        $uploadResult['normalized_alignment'] = $normalized;

        return $uploadResult;
    }

    /**
     * Generate speech with timestamps and save to database
     */
    public function generateWithTimestampsAndStore(string $key, string $text, string $channel = 'voice'): \App\Models\AudioTrack
    {
        // Check if already exists
        $existing = \App\Models\AudioTrack::where('key', $key)->first();
        if ($existing) {
            Log::info('Audio track already exists', ['key' => $key]);
            return $existing;
        }

        $result = $this->convertWithTimestamps($text);

        $duration = $this->estimateDuration($text);

        $track = \App\Models\AudioTrack::create([
            'key' => $key,
            'channel' => $channel,
            's3_path' => $result['path'],
            's3_url' => $result['url'],
            'duration_ms' => $duration,
            'default_volume' => $this->getDefaultVolume($channel),
            'metadata' => [
                'text' => $text,
                'generated_at' => now()->toIso8601String(),
                'elevenlabs' => $result['elevenlabs'] ?? null,
                'alignment' => $result['alignment'] ?? null,
                'normalized_alignment' => $result['normalized_alignment'] ?? null,
            ],
            'is_preloaded' => $this->shouldPreload($key),
        ]);

        // Record usage
        try {
            if (!empty($result['elevenlabs']['character_count']) || !empty($result['elevenlabs']['request_id'])) {
                \App\Models\TTSUsage::create([
                    'audio_track_id' => $track->id,
                    'request_id' => $result['elevenlabs']['request_id'] ?? null,
                    'character_count' => isset($result['elevenlabs']['character_count']) ? (int) $result['elevenlabs']['character_count'] : null,
                    'status_code' => $result['elevenlabs']['status_code'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to record TTS usage', ['error' => $e->getMessage(), 'track' => $track->id]);
        }

        return $track;
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
        $track = \App\Models\AudioTrack::create([
            'key' => $key,
            'channel' => $channel,
            's3_path' => $result['path'],
            's3_url' => $result['url'],
            'duration_ms' => $duration,
            'default_volume' => $this->getDefaultVolume($channel),
            'metadata' => [
                'text' => $text,
                'generated_at' => now()->toIso8601String(),
                'elevenlabs' => $result['elevenlabs'] ?? null,
            ],
            'is_preloaded' => $this->shouldPreload($key),
        ]);

        // Record TTS usage (character count, request id) for auditing/billing
        try {
            if (!empty($result['elevenlabs']['character_count']) || !empty($result['elevenlabs']['request_id'])) {
                \App\Models\TTSUsage::create([
                    'audio_track_id' => $track->id,
                    'request_id' => $result['elevenlabs']['request_id'] ?? null,
                    'character_count' => isset($result['elevenlabs']['character_count']) ? (int) $result['elevenlabs']['character_count'] : null,
                    'status_code' => $result['elevenlabs']['status_code'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to record TTS usage', ['error' => $e->getMessage(), 'track' => $track->id]);
        }

        return $track;
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
