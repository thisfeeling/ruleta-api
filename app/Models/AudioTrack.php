<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class AudioTrack extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'channel',
        's3_path',
        's3_url',
        'duration_ms',
        'default_volume',
        'metadata',
        'is_preloaded',
    ];

    protected $casts = [
        'default_volume' => 'float',
        'metadata' => 'array',
        'is_preloaded' => 'boolean',
    ];

    // Relationships
    public function plays(): HasMany
    {
        return $this->hasMany(AudioPlay::class, 'track_id');
    }

    // Scopes
    public function scopeChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopePreloaded($query)
    {
        return $query->where('is_preloaded', true);
    }

    public function scopeKey($query, string $key)
    {
        return $query->where('key', $key);
    }

    // Helpers
    public function getSignedUrl(int $expiresInMinutes = 60): ?string
    {
        // Use configured filesystem disk (driven by FILESYSTEM_DISK env via config)
        $disk = config('filesystems.default');

        try {
            // Prefer signed URLs for remote S3-like disks
            if (in_array($disk, ['s3', 'rustfs'])) {
                return \Storage::disk($disk)->temporaryUrl(
                    $this->s3_path,
                    now()->addMinutes($expiresInMinutes)
                );
            }

            // For local/public disks prefer stored absolute URL (s3_url) if present
            if ($this->s3_url) {
                return $this->s3_url;
            }

            return \Storage::disk($disk)->url($this->s3_path);
        } catch (\Throwable $e) {
            // Fallback to whatever absolute URL we have stored (s3_url) or null
            return $this->s3_url ?? null;
        }
    }

    public function recordPlay(?int $showId = null, ?int $userId = null, ?string $context = null): void
    {
        $this->plays()->create([
            'show_id' => $showId,
            'played_by' => $userId,
            'played_at' => now(),
            'context' => $context,
        ]);
    }
}
