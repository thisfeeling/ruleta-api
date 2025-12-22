<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameInstruction extends Model
{
    use HasFactory;

    protected $fillable = [
        'show_id',
        'game_id',
        'game_type',
        'locale',
        'title_es',
        'title_en',
        'body_es',
        'body_en',
        'content_es',
        'content_en',
        'audio_es_url',
        'audio_en_url',
        'estimated_duration_seconds',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
