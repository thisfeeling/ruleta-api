<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordSearchGrid extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'grid',
        'words',
        'metadata',
    ];

    protected $casts = [
        'grid' => 'array',
        'words' => 'array',
        'metadata' => 'array',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
