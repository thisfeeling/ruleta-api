<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\{Player, Game, GameInstruction};

class InstructionRead extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'player_id',
        'instruction_id',
        'completed',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'completed' => 'boolean',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function instruction()
    {
        return $this->belongsTo(GameInstruction::class, 'instruction_id');
    }
}
