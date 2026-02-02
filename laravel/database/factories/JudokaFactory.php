<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Judoka;
use App\Models\Toernooi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Judoka>
 */
class JudokaFactory extends Factory
{
    protected $model = Judoka::class;

    public function definition(): array
    {
        $geslacht = fake()->randomElement(['M', 'V']);
        $geboortejaar = fake()->numberBetween(date('Y') - 15, date('Y') - 5);
        $leeftijd = date('Y') - $geboortejaar;

        // Determine realistic weight based on age
        $gewicht = match (true) {
            $leeftijd <= 6 => fake()->randomFloat(1, 16, 28),
            $leeftijd <= 9 => fake()->randomFloat(1, 22, 40),
            $leeftijd <= 12 => fake()->randomFloat(1, 28, 55),
            default => fake()->randomFloat(1, 35, 80),
        };

        // Determine leeftijdsklasse
        $leeftijdsklasse = match (true) {
            $leeftijd <= 6 => "mini's",
            $leeftijd <= 9 => 'pupillen',
            $leeftijd <= 12 => 'aspiranten',
            default => 'cadetten',
        };

        return [
            'toernooi_id' => Toernooi::factory(),
            'club_id' => Club::factory(),
            'naam' => fake()->lastName() . ', ' . fake()->firstName($geslacht === 'M' ? 'male' : 'female'),
            'geboortejaar' => $geboortejaar,
            'geslacht' => $geslacht,
            'band' => fake()->randomElement(['wit', 'geel', 'oranje', 'groen', 'blauw']),
            'gewicht' => $gewicht,
            'leeftijdsklasse' => $leeftijdsklasse,
            'gewichtsklasse' => '-' . (ceil($gewicht / 4) * 4), // Round up to nearest 4kg
            'aanwezigheid' => 'onbekend',
            'is_onvolledig' => false,
        ];
    }

    /**
     * Create a judoka who is present and weighed.
     */
    public function aanwezig(): static
    {
        return $this->state(function (array $attributes) {
            $gewicht = $attributes['gewicht'] ?? 30;
            return [
                'aanwezigheid' => 'aanwezig',
                'gewicht_gewogen' => $gewicht + fake()->randomFloat(1, -0.5, 0.5),
            ];
        });
    }

    /**
     * Create a judoka who is absent.
     */
    public function afwezig(): static
    {
        return $this->state(fn (array $attributes) => [
            'aanwezigheid' => 'afwezig',
        ]);
    }

    /**
     * Create a mini (age 4-6).
     */
    public function mini(): static
    {
        $geboortejaar = date('Y') - fake()->numberBetween(4, 6);

        return $this->state(fn (array $attributes) => [
            'geboortejaar' => $geboortejaar,
            'leeftijdsklasse' => "mini's",
            'gewicht' => fake()->randomFloat(1, 16, 28),
            'band' => fake()->randomElement(['wit', 'geel']),
        ]);
    }

    /**
     * Create a pupil (age 7-9).
     */
    public function pupil(): static
    {
        $geboortejaar = date('Y') - fake()->numberBetween(7, 9);

        return $this->state(fn (array $attributes) => [
            'geboortejaar' => $geboortejaar,
            'leeftijdsklasse' => 'pupillen',
            'gewicht' => fake()->randomFloat(1, 22, 40),
            'band' => fake()->randomElement(['wit', 'geel', 'oranje']),
        ]);
    }

    /**
     * Create a judoka with incomplete data.
     */
    public function onvolledig(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_onvolledig' => true,
            'gewicht' => null,
            'import_status' => 'te_corrigeren',
            'import_warnings' => json_encode(['Gewicht ontbreekt']),
        ]);
    }

    /**
     * Create a judoka who has paid.
     */
    public function betaald(): static
    {
        return $this->state(fn (array $attributes) => [
            'betaald_op' => now(),
            'betaling_id' => 'tr_' . fake()->regexify('[A-Za-z0-9]{10}'),
        ]);
    }
}
