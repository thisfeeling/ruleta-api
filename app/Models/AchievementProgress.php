<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AchievementProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'achievement_id',
        'current_progress',
        'required_progress',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }
}
