<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TTSUsage extends Model
{
    use HasFactory;

    protected $table = 'tts_usages';

    protected $fillable = [
        'audio_track_id',
        'request_id',
        'character_count',
        'status_code',
    ];

    public function track()
    {
        return $this->belongsTo(AudioTrack::class, 'audio_track_id');
    }
}
