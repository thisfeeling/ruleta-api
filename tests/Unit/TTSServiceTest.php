<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Services\TTS\TTSService;
use App\Models\AudioTrack;

class TTSServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_and_store_records_headers_and_uploads()
    {
        // Fake ElevenLabs response with headers
        $binary = 'FAKE_MP3_CONTENT';

        Http::fake([
            'https://api.elevenlabs.io/*' => Http::response($binary, 200, [
                'Content-Type' => 'audio/mpeg',
                'x-character-count' => '256',
                'request-id' => 'req-abc',
            ]),
        ]);

        Storage::fake('s3');

        $tts = app(TTSService::class);

        $track = $tts->generateAndStore('narrator.test', 'Hola mundo');

        // Assert DB row
        $this->assertDatabaseHas('audio_tracks', ['key' => 'narrator.test']);

        $track->refresh();

        $this->assertArrayHasKey('elevenlabs', $track->metadata);
        $this->assertSame('256', (string) $track->metadata['elevenlabs']['character_count']);
        $this->assertSame('req-abc', $track->metadata['elevenlabs']['request_id']);

        // Storage asserted exists
        Storage::disk('s3')->assertExists($track->s3_path);
    }

    public function test_generate_throws_on_error_response()
    {
        Http::fake([
            'https://api.elevenlabs.io/*' => Http::response('{"error":"bad"}', 400),
        ]);

        $this->expectException(\Exception::class);

        app(TTSService::class)->generate('Texto de prueba');
    }

    public function test_convert_with_timestamps_and_store_saves_alignment_and_usage()
    {
        $audioBase64 = base64_encode('FAKE_MP3_CONTENT');

        Http::fake([
            'https://api.elevenlabs.io/*' => Http::response([
                'audio_base64' => $audioBase64,
                'alignment' => [
                    'characters' => ['H','i'],
                    'character_start_times_seconds' => [0,0.1],
                    'character_end_times_seconds' => [0.1,0.2],
                ],
                'normalized_alignment' => [
                    'characters' => ['H','i'],
                    'character_start_times_seconds' => [0,0.1],
                    'character_end_times_seconds' => [0.1,0.2],
                ],
            ], 200, [
                'Content-Type' => 'application/json',
                'x-character-count' => '42',
                'request-id' => 'req-ts',
            ]),
        ]);

        Storage::fake('s3');

        $tts = app(TTSService::class);

        $track = $tts->generateWithTimestampsAndStore('narrator.ts', 'Hi');

        $this->assertDatabaseHas('audio_tracks', ['key' => 'narrator.ts']);

        $track->refresh();

        $this->assertArrayHasKey('alignment', $track->metadata);
        $this->assertArrayHasKey('normalized_alignment', $track->metadata);
        $this->assertSame('42', (string) $track->metadata['elevenlabs']['character_count']);
        $this->assertSame('req-ts', $track->metadata['elevenlabs']['request_id']);

        Storage::disk('s3')->assertExists($track->s3_path);

        // Check tts_usages created
        $this->assertDatabaseHas('tts_usages', ['audio_track_id' => $track->id, 'request_id' => 'req-ts', 'character_count' => 42]);
    }
}
