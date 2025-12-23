<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordSearchFind extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'grid_id',
        'word',
        'find_order',
        'time_elapsed_ms',
        'found_at',
    ];

    protected $casts = [
        'found_at' => 'datetime',
        'time_elapsed_ms' => 'integer',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function grid(): BelongsTo
    {
        return $this->belongsTo(WordSearchGrid::class, 'grid_id');
    }
}
