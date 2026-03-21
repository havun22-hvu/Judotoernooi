<?php

namespace Database\Factories;

use App\Models\Judoka;
use App\Models\Poule;
use App\Models\Wedstrijd;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wedstrijd>
 */
class WedstrijdFactory extends Factory
{
    protected $model = Wedstrijd::class;

    public function definition(): array
    {
        return [
            'poule_id' => Poule::factory(),
            'judoka_wit_id' => Judoka::factory(),
            'judoka_blauw_id' => Judoka::factory(),
            'volgorde' => fake()->numberBetween(1, 10),
            'is_gespeeld' => false,
            'winnaar_id' => null,
            'score_wit' => null,
            'score_blauw' => null,
            'uitslag_type' => null,
        ];
    }

    /**
     * Create a match that has been played.
     */
    public function gespeeld(): static
    {
        return $this->state(function (array $attributes) {
            $winnaarIsWit = fake()->boolean();
            $scoreWinnaar = fake()->randomElement([1, 2]);
            $scoreVerliezer = fake()->randomElement([0, 1]);

            return [
                'is_gespeeld' => true,
                'gespeeld_op' => now(),
                'winnaar_id' => $winnaarIsWit
                    ? $attributes['judoka_wit_id']
                    : $attributes['judoka_blauw_id'],
                'score_wit' => $winnaarIsWit ? $scoreWinnaar : $scoreVerliezer,
                'score_blauw' => $winnaarIsWit ? $scoreVerliezer : $scoreWinnaar,
                'uitslag_type' => fake()->randomElement(['ippon', 'waza-ari', 'beslissing']),
            ];
        });
    }

    /**
     * Create an elimination match.
     */
    public function eliminatie(string $groep = 'A', string $ronde = 'kwart'): static
    {
        return $this->state(fn (array $attributes) => [
            'groep' => $groep,
            'ronde' => $ronde,
            'bracket_positie' => fake()->numberBetween(1, 8),
        ]);
    }

    /**
     * Create a match won by ippon.
     */
    public function ippon(): static
    {
        return $this->gespeeld()->state(fn (array $attributes) => [
            'uitslag_type' => 'ippon',
            'score_wit' => $attributes['winnaar_id'] === $attributes['judoka_wit_id'] ? 2 : 0,
            'score_blauw' => $attributes['winnaar_id'] === $attributes['judoka_blauw_id'] ? 2 : 0,
        ]);
    }

    /**
     * Create a BYE match (opponent missing).
     */
    public function bye(): static
    {
        return $this->state(fn (array $attributes) => [
            'judoka_blauw_id' => null,
            'is_gespeeld' => true,
            'winnaar_id' => $attributes['judoka_wit_id'],
            'uitslag_type' => 'bye',
        ]);
    }
}
