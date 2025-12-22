<?php

namespace Database\Factories;

use App\Models\PlayerScore;
use App\Models\Player;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerScore> */
class PlayerScoreFactory extends Factory
{
    protected $model = PlayerScore::class;

    public function definition(): array
    {
        $raw = $this->faker->numberBetween(0,1000);
        return [
            'player_id' => Player::factory(),
            'game_id' => Game::factory(),
            'game_type' => $this->faker->randomElement(['millionaire','rope','spell','roulette','word_search','flappy']),
            'raw_score' => $raw,
            'normalized_score' => min(1000, $raw),
            'metadata' => [],
        ];
    }
}
