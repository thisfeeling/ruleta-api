<?php

namespace Database\Factories;

use App\Models\AudioTrack;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AudioTrack> */
class AudioTrackFactory extends Factory
{
    protected $model = AudioTrack::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->uuid(),
            'channel' => $this->faker->randomElement(['music','sfx','voice']),
            's3_path' => 'audio/' . $this->faker->uuid() . '.mp3',
            's3_url' => 'https://s3.example/' . $this->faker->uuid() . '.mp3',
            'duration_ms' => $this->faker->numberBetween(500, 120000),
            'default_volume' => $this->faker->randomFloat(2, 0.1, 1.0),
            'metadata' => [],
            'is_preloaded' => $this->faker->boolean(30),
        ];
    }
}
