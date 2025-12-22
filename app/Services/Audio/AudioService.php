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
