<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MillionaireQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'question',
        'options',
        'correct_option',
        'metadata',
    ];

    protected $casts = [
        'options' => 'array',
        'metadata' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
