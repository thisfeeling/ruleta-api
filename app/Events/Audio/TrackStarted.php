<?php

namespace App\Events\Audio;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrackStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $showId,
        public string $trackKey,
        public string $channel,
        public string $signedUrl,
        public ?int $durationMs = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("show.{$this->showId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'audio.track_started';
    }

    public function broadcastWith(): array
    {
        return [
            'track_key' => $this->trackKey,
            'channel' => $this->channel,
            'signed_url' => $this->signedUrl,
            'duration_ms' => $this->durationMs,
            'started_at' => now()->toIso8601String(),
        ];
    }
}
