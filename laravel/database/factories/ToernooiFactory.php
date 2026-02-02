<?php

namespace Database\Factories;

use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Toernooi>
 */
class ToernooiFactory extends Factory
{
    protected $model = Toernooi::class;

    public function definition(): array
    {
        $naam = fake()->randomElement([
            'Herfsttoernooi',
            'Voorjaarstoernooi',
            'Clubkampioenschap',
            'Open Toernooi',
            'Jeugdtoernooi',
        ]) . ' ' . fake()->year();

        return [
            'organisator_id' => Organisator::factory(),
            'naam' => $naam,
            'slug' => Str::slug($naam),
            'organisatie' => fake()->company(),
            'datum' => fake()->dateTimeBetween('+1 week', '+3 months'),
            'locatie' => fake()->city() . ' Sporthal',
            'verwacht_aantal_judokas' => fake()->numberBetween(50, 200),
            'aantal_matten' => fake()->numberBetween(2, 6),
            'aantal_blokken' => fake()->numberBetween(2, 4),
            'gewicht_tolerantie' => 0.5,
            'weging_verplicht' => true,
            'max_wegingen' => 2,
            'judokas_per_coach' => 8,
        ];
    }

    /**
     * Create a freemium (free tier) tournament.
     */
    public function freemium(): static
    {
        return $this->state(fn (array $attributes) => [
            // Freemium fields depend on migration existence
        ]);
    }

    /**
     * Create a tournament that is closed/finished.
     */
    public function afgesloten(): static
    {
        return $this->state(fn (array $attributes) => [
            'afgesloten_at' => now(),
            'datum' => fake()->dateTimeBetween('-3 months', '-1 week'),
        ]);
    }

    /**
     * Create a tournament with dynamic weight classes.
     */
    public function dynamischeKlassen(): static
    {
        return $this->state(fn (array $attributes) => [
            'gebruik_gewichtsklassen' => false,
            'gewichtsklassen' => [
                'minis' => [
                    'label' => "Mini's 4-6j",
                    'min_leeftijd' => 4,
                    'max_leeftijd' => 6,
                    'max_kg_verschil' => 3,
                    'max_leeftijd_verschil' => 1,
                ],
                'pupillen' => [
                    'label' => 'Pupillen 7-9j',
                    'min_leeftijd' => 7,
                    'max_leeftijd' => 9,
                    'max_kg_verschil' => 4,
                    'max_leeftijd_verschil' => 1,
                ],
            ],
        ]);
    }

    /**
     * Create a tournament with fixed weight classes.
     */
    public function vasteKlassen(): static
    {
        return $this->state(fn (array $attributes) => [
            'gebruik_gewichtsklassen' => true,
            'gewichtsklassen' => [
                'minis' => [
                    'label' => "Mini's 4-6j",
                    'min_leeftijd' => 4,
                    'max_leeftijd' => 6,
                    'max_kg_verschil' => 0,
                    'gewichten' => ['-20', '-24', '-28', '+28'],
                ],
            ],
        ]);
    }

    /**
     * Create a tournament scheduled for today (wedstrijddag).
     */
    public function wedstrijddag(): static
    {
        return $this->state(fn (array $attributes) => [
            'datum' => today(),
            'voorbereiding_klaar_op' => now()->subHours(2),
        ]);
    }
}
