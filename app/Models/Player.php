<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, BelongsToMany};

class Player extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'show_id',
        'user_id',
        'player_number',
        'pin',
        'status',
        'elimination_order',
        'eliminated_by_game',
        'total_score',
        'stats',
    ];

    protected $casts = [
        'stats' => 'array',
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

    public function scores(): HasMany
    {
        return $this->hasMany(PlayerScore::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(PlayerAchievement::class);
    }

    public function achievementProgress(): HasMany
    {
        return $this->hasMany(AchievementProgress::class);
    }

    public function millionaireAnswers(): HasMany
    {
        return $this->hasMany(MillionaireAnswer::class);
    }

    public function spellWords(): HasMany
    {
        return $this->hasMany(SpellWord::class);
    }

    public function rouletteSpins(): HasMany
    {
        return $this->hasMany(RouletteSpin::class);
    }

    public function wordSearchFinds(): HasMany
    {
        return $this->hasMany(WordSearchFind::class);
    }

    public function flappyAttempts(): HasMany
    {
        return $this->hasMany(FlappyAttempt::class);
    }

    public function ropeGroups(): BelongsToMany
    {
        return $this->belongsToMany(RopeGroup::class, 'group_player')
            ->withPivot('clicks_contributed')
            ->withTimestamps();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeEliminated($query)
    {
        return $query->where('status', 'eliminated');
    }

    public function scopeInShow($query, int $showId)
    {
        return $query->where('show_id', $showId);
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isEliminated(): bool
    {
        return $this->status === 'eliminated';
    }

    public function eliminate(string $game, int $order): void
    {
        $this->update([
            'status' => 'eliminated',
            'eliminated_by_game' => $game,
            'elimination_order' => $order,
        ]);
    }

    public function addScore(?int $gameId, string $gameType, int $rawScore, int $normalizedScore, ?array $metadata = null): PlayerScore
    {
        return $this->scores()->create([
            'game_id' => $gameId,
            'game_type' => $gameType,
            'raw_score' => $rawScore,
            'normalized_score' => $normalizedScore,
            'metadata' => $metadata,
        ]);
    }
}
