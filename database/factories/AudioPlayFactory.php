<?php

namespace Database\Factories;

use App\Models\AudioPlay;
use App\Models\Show;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AudioPlay> */
class AudioPlayFactory extends Factory
{
    protected $model = AudioPlay::class;

    public function definition(): array
    {
        return [
            'track_id' => \App\Models\AudioTrack::factory(),
            'show_id' => Show::factory(),
            'played_by' => Player::factory(),
            'played_at' => now(),
            'context' => 'spell_attempt',
            'approved' => null,
            'reviewed_by' => null,
        ];
    }
}
