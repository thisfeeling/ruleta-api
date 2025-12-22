<?php

namespace App\Listeners;

use App\Events\Audio\TrackStarted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class RecordAudioPlay implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TrackStarted $event): void
    {
        // Build context metadata
        $context = [
            'track_key' => $event->trackKey,
            'channel' => $event->channel,
            'signed_url' => $event->signedUrl,
            'duration_ms' => $event->durationMs,
        ];

        try {
            $track = \App\Models\AudioTrack::where('key', $event->trackKey)->first();

            if ($track) {
                // Use helper to create play attached to track (context is string)
                $track->recordPlay($event->showId, null, json_encode($context));
            } else {
                // Create a lightweight AudioTrack record so we can maintain referential integrity
                $track = \App\Models\AudioTrack::create([
                    'key' => $event->trackKey,
                    'channel' => $event->channel,
                    's3_path' => '',
                    's3_url' => $event->signedUrl ?? '',
                    'duration_ms' => $event->durationMs ?? null,
                    'default_volume' => 1.0,
                    'metadata' => [],
                    'is_preloaded' => false,
                ]);

                $track->recordPlay($event->showId, null, json_encode($context));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to record audio play', [
                'error' => $e->getMessage(),
                'track_key' => $event->trackKey,
                'show_id' => $event->showId,
            ]);
            throw $e; // allow queue worker to retry
        }
    }
}
