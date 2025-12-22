<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'show_id',
        'user_id',
        'event_type',
        'entity_type',
        'entity_id',
        'payload',
        'ip_address',
        'user_agent',
        's3_backup_key',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    // Relationships
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeEventType($query, string $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeForShow($query, int $showId)
    {
        return $query->where('show_id', $showId);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>', now()->subDays($days));
    }

    // Helpers
    public static function log(
        string $eventType,
        array $payload,
        ?int $showId = null,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): self {
        return self::create([
            'show_id' => $showId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $payload,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
