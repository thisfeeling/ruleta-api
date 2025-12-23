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
        'question_number',
        'question_text_es',
        'question_text_en',
        'option_a_es',
        'option_a_en',
        'option_b_es',
        'option_b_en',
        'option_c_es',
        'option_c_en',
        'option_d_es',
        'option_d_en',
        'correct_answer',
        'time_limit_seconds',
        'audio_question_url',
    ];

    protected $casts = [
        'time_limit_seconds' => 'integer',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
