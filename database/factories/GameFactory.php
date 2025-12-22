<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Show;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Game> */
class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        return [
            'show_id' => Show::factory(),
            'type' => $this->faker->randomElement(['millionaire','rope','spell','roulette','word_search','flappy']),
            'round_number' => $this->faker->numberBetween(1,5),
            'status' => 'pending',
            'is_bonus' => false,
            'players_at_start' => 0,
            'players_eliminated' => 0,
            'config' => [],
            'state' => [],
        ];
    }
}
