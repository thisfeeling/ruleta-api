<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\AudioTrack;
use Illuminate\Support\Facades\Storage;
use Mockery;

class AudioTrackTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_signed_url_uses_filesystem_env_and_temporary_url()
    {
        config(['filesystems.default' => 'rustfs']);

        $disk = Mockery::mock();
        $disk->shouldReceive('temporaryUrl')
            ->once()
            ->with('audio/test.mp3', Mockery::on(function ($expiry) {
                return $expiry instanceof \DateTimeInterface;
            }))
            ->andReturn('signed-url-rustfs');

        Storage::shouldReceive('disk')->with('rustfs')->andReturn($disk);

        $audio = AudioTrack::factory()->make([
            's3_path' => 'audio/test.mp3',
            's3_url' => 'https://example.com/audio/test.mp3',
        ]);

        $this->assertSame('signed-url-rustfs', $audio->getSignedUrl(15));
    }

    public function test_get_signed_url_prefers_s3_url_for_local_disk_if_present()
    {
        config(['filesystems.default' => 'local']);

        $audio = AudioTrack::factory()->make([
            's3_path' => 'audio/test.mp3',
            's3_url' => 'https://example.com/audio/test.mp3',
        ]);

        $this->assertNotNull($audio->s3_url, 'Factory should set s3_url');

        // Sanity checks to help debug
        $this->assertSame('local', config('filesystems.default'), 'FILESYSTEM_DISK should be local');

        $this->assertSame('https://example.com/audio/test.mp3', $audio->getSignedUrl());
    }

    public function test_get_signed_url_uses_disk_url_when_s3_url_missing()
    {
        config(['filesystems.default' => 'local']);

        $disk = Mockery::mock();
        $disk->shouldReceive('url')
            ->once()
            ->with('audio/test.mp3')
            ->andReturn('/storage/audio/test.mp3');

        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        $audio = AudioTrack::factory()->make([
            's3_path' => 'audio/test.mp3',
            's3_url' => null,
        ]);

        $this->assertSame('/storage/audio/test.mp3', $audio->getSignedUrl());
    }

    public function test_get_signed_url_returns_s3_url_on_exception()
    {
        config(['filesystems.default' => 'rustfs']);

        $disk = Mockery::mock();
        $disk->shouldReceive('temporaryUrl')
            ->once()
            ->andThrow(new \Exception('no network'));

        Storage::shouldReceive('disk')->with('rustfs')->andReturn($disk);

        $audio = AudioTrack::factory()->make([
            's3_path' => 'audio/test.mp3',
            's3_url' => 'https://example.com/audio/test.mp3',
        ]);

        $this->assertSame('https://example.com/audio/test.mp3', $audio->getSignedUrl());
    }
}
