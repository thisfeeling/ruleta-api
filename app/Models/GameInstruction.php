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
        'locale',
        'title_es',
        'title_en',
        'body_es',
        'body_en',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
