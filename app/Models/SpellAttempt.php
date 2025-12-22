<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpellAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'word_id',
        'attempt',
        'is_correct',
        'time_ms',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(SpellWord::class, 'word_id');
    }
}
