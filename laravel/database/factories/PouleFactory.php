<?php

namespace Database\Factories;

use App\Models\Blok;
use App\Models\Mat;
use App\Models\Poule;
use App\Models\Toernooi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Poule>
 */
class PouleFactory extends Factory
{
    protected $model = Poule::class;

    public function definition(): array
    {
        static $nummer = 1;

        $leeftijdsklasse = fake()->randomElement(["mini's", 'pupillen', 'aspiranten']);
        $gewichtsklasse = '-' . fake()->randomElement([20, 24, 28, 32, 36, 40]);

        return [
            'toernooi_id' => Toernooi::factory(),
            'blok_id' => null,
            'mat_id' => null,
            'nummer' => $nummer++,
            'titel' => "{$leeftijdsklasse} {$gewichtsklasse} Poule",
            'leeftijdsklasse' => $leeftijdsklasse,
            'gewichtsklasse' => $gewichtsklasse,
            'type' => 'poule',
            'aantal_judokas' => 0,
            'aantal_wedstrijden' => 0,
            'is_vast' => false,
            'spreker_klaar' => false,
        ];
    }

    /**
     * Create a poule assigned to a blok and mat.
     */
    public function ingedeeld(): static
    {
        return $this->state(fn (array $attributes) => [
            'blok_id' => Blok::factory(),
            'mat_id' => Mat::factory(),
        ]);
    }

    /**
     * Create an elimination bracket poule.
     */
    public function eliminatie(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'eliminatie',
            'titel' => str_replace('Poule', 'Eliminatie', $attributes['titel'] ?? 'Eliminatie'),
        ]);
    }

    /**
     * Create a poule that is completed (spreker klaar).
     */
    public function afgerond(): static
    {
        return $this->state(fn (array $attributes) => [
            'spreker_klaar' => true,
        ]);
    }

    /**
     * Create a poule with specific judoka count.
     */
    public function metJudokas(int $aantal): static
    {
        $wedstrijden = ($aantal * ($aantal - 1)) / 2;

        return $this->state(fn (array $attributes) => [
            'aantal_judokas' => $aantal,
            'aantal_wedstrijden' => $wedstrijden,
        ]);
    }
}
