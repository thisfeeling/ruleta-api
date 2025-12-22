<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Audio\AudioService;
use App\Models\AudioTrack;

class AudioServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_signed_urls_returns_map_for_keys()
    {
        $service = app(AudioService::class);

        $a = AudioTrack::factory()->create(['key' => 'sfx.click', 's3_path' => 'audio/click.mp3', 's3_url' => 'https://example.com/click.mp3']);
        $b = AudioTrack::factory()->create(['key' => 'sfx.wrong', 's3_path' => 'audio/wrong.mp3', 's3_url' => 'https://example.com/wrong.mp3']);

        $map = $service->getSignedUrls(['sfx.click', 'sfx.wrong']);

        $this->assertArrayHasKey('sfx.click', $map);
        $this->assertArrayHasKey('sfx.wrong', $map);
        $this->assertSame($a->getSignedUrl(), $map['sfx.click']);
    }
}
