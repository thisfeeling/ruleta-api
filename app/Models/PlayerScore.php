<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'game_id',
        'game_type',
        'raw_score',
        'normalized_score',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Relationships
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    // Scopes
    public function scopeForGame($query, string $gameType)
    {
        return $query->where('game_type', $gameType);
    }

    public function scopeTopScores($query, int $limit = 10)
    {
        return $query->orderByDesc('normalized_score')->limit($limit);
    }

    // Static normalizers (0-1000 scale)
    public static function normalizeMillionaire(int $correct, int $total): int
    {
        if ($total === 0) return 0;
        return (int) (($correct / $total) * 1000);
    }

    public static function normalizeRope(int $clicks): int
    {
        return min(1000, (int) (($clicks / 200) * 1000));
    }

    public static function normalizeSpell(bool $correct, int $timeTakenMs, int $timeLimit): int
    {
        if (!$correct) return 0;
        $timeRatio = $timeTakenMs / ($timeLimit * 1000);
        return (int) ((1 - $timeRatio) * 1000);
    }

    public static function normalizeRoulette(int $totalPoints): int
    {
        return min(1000, $totalPoints);
    }

    public static function normalizeWordSearch(int $wordsFound, int $timeMs): int
    {
        $baseScore = $wordsFound * 100;
        $timeBonus = max(0, (180000 - $timeMs) / 180);
        return min(1000, (int) ($baseScore + $timeBonus));
    }

    public static function normalizeFlappy(int $survivalTimeMs): int
    {
        return min(1000, (int) ($survivalTimeMs / 100));
    }
}
