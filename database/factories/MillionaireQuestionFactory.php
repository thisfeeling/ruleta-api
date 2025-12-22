<?php

namespace Database\Factories;

use App\Models\MillionaireQuestion;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MillionaireQuestion> */
class MillionaireQuestionFactory extends Factory
{
    protected $model = MillionaireQuestion::class;

    public function definition(): array
    {
        $options = [$this->faker->word(), $this->faker->word(), $this->faker->word(), $this->faker->word()];
        $correct = $this->faker->numberBetween(0,3);
        return [
            'game_id' => Game::factory(),
            'question' => $this->faker->sentence(),
            'options' => $options,
            'correct_option' => $correct,
            'metadata' => [],
        ];
    }
}
