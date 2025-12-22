<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudioPlay extends Model
{
    use HasFactory;

    protected $fillable = [
        'track_id',
        'show_id',
        'played_by',
        'played_at',
        'context',
    ];

    protected $casts = [
        'played_at' => 'datetime',
    ];

    public function track(): BelongsTo
    {
        return $this->belongsTo(AudioTrack::class, 'track_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'played_by');
    }
}
