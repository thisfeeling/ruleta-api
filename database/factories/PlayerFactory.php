<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Show;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Player> */
class PlayerFactory extends Factory
{
    protected $model = Player::class;

    public function definition(): array
    {
        return [
            'show_id' => Show::factory(),
            'user_id' => User::factory(),
            'player_number' => $this->faker->unique()->numberBetween(1,50),
            'pin' => str_pad($this->faker->numberBetween(0,9999), 4, '0', STR_PAD_LEFT),
            'status' => 'active',
            'elimination_order' => null,
            'eliminated_by_game' => null,
            'total_score' => 0,
            'stats' => [],
        ];
    }
}
