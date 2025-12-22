<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstructionRead extends Model
{
    use HasFactory;

    protected $fillable = [
        'instruction_id',
        'player_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];
}
