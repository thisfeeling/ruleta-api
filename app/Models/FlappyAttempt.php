<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlappyAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'game_id',
        'survival_time_ms',
        'score',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
