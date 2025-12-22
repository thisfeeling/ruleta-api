<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{Show, AudioTrack, AudioPlay};
use App\Events\Audio\TrackStarted;

class RecordAudioPlayTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_play_for_known_track()
    {
        $show = Show::factory()->create();
        $track = AudioTrack::factory()->create(['key' => 'narration_test', 'channel' => 'voice']);

        event(new TrackStarted($show->id, 'narration_test', 'voice', 'https://example.com/test.mp3', 12000));

        $this->assertDatabaseHas('audio_plays', [
            'track_id' => $track->id,
            'show_id' => $show->id,
        ]);

        $play = AudioPlay::where('track_id', $track->id)->first();
        $this->assertNotNull($play->played_at);
        $this->assertNotNull($play->context);
        $context = json_decode($play->context, true);
        $this->assertIsArray($context);
        $this->assertEquals('narration_test', $context['track_key']);
    }

    public function test_records_play_for_unknown_track()
    {
        $show = Show::factory()->create();

        event(new TrackStarted($show->id, 'unknown_key', 'sfx', 'https://example.com/x.mp3', 5000));

        // Unknown key will create a lightweight AudioTrack and record play
        $this->assertDatabaseHas('audio_tracks', ['key' => 'unknown_key']);

        $track = AudioTrack::where('key', 'unknown_key')->first();
        $this->assertNotNull($track);

        $this->assertDatabaseHas('audio_plays', [
            'track_id' => $track->id,
            'show_id' => $show->id,
        ]);

        $play = AudioPlay::where('track_id', $track->id)->first();
        $this->assertNotNull($play->context);
        $context = json_decode($play->context, true);
        $this->assertIsArray($context);
        $this->assertEquals('unknown_key', $context['track_key']);
    }
}
