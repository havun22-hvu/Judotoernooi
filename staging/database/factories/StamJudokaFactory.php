<?php

namespace Database\Factories;

use App\Models\Organisator;
use App\Models\StamJudoka;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StamJudoka>
 */
class StamJudokaFactory extends Factory
{
    protected $model = StamJudoka::class;

    public function definition(): array
    {
        return [
            'organisator_id' => Organisator::factory(),
            'naam' => fake()->name(),
            'geboortejaar' => fake()->numberBetween(2010, 2020),
            'geslacht' => fake()->randomElement(['M', 'V']),
            'band' => fake()->randomElement(['wit', 'geel', 'oranje', 'groen']),
            'actief' => true,
            'wimpel_punten_totaal' => 0,
            'wimpel_is_nieuw' => false,
        ];
    }

    public function metPunten(int $punten): static
    {
        return $this->state(fn () => [
            'wimpel_punten_totaal' => $punten,
        ]);
    }

    public function nieuw(): static
    {
        return $this->state(fn () => [
            'wimpel_is_nieuw' => true,
        ]);
    }
}
