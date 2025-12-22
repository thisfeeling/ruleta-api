<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    public function getSignedUrl(int $expiresInMinutes = 60): string
    {
        return \Storage::disk('s3')->temporaryUrl(
            $this->s3_path,
            now()->addMinutes($expiresInMinutes)
        );
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
