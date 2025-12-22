<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'show_id',
        'type',
        'round_number',
        'status',
        'is_bonus',
        'players_at_start',
        'players_eliminated',
        'config',
        'state',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'is_bonus' => 'boolean',
        'config' => 'array',
        'state' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships
    public function show(): BelongsTo
    {
        return $this->belongsTo(Show::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(PlayerScore::class);
    }

    public function millionaireQuestions(): HasMany
    {
        return $this->hasMany(MillionaireQuestion::class);
    }

    public function ropeGroups(): HasMany
    {
        return $this->hasMany(RopeGroup::class);
    }

    public function spellWords(): HasMany
    {
        return $this->hasMany(SpellWord::class);
    }

    public function rouletteSpins(): HasMany
    {
        return $this->hasMany(RouletteSpin::class);
    }

    public function wordSearchGrid(): HasMany
    {
        return $this->hasMany(WordSearchGrid::class);
    }

    public function flappyAttempts(): HasMany
    {
        return $this->hasMany(FlappyAttempt::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeBonus($query)
    {
        return $query->where('is_bonus', true);
    }

    public function scopeMain($query)
    {
        return $query->where('is_bonus', false);
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function start(): void
    {
        $this->update([
            'status' => 'active',
            'started_at' => now(),
            'players_at_start' => $this->show->getActivePlayerCount(),
        ]);
    }

    public function complete(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
