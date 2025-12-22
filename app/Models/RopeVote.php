<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RopeVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'voter_player_id',
        'target_player_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(RopeGroup::class, 'group_id');
    }
}
