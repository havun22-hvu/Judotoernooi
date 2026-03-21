<?php

namespace Database\Factories;

use App\Models\Blok;
use App\Models\Toernooi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Blok>
 */
class BlokFactory extends Factory
{
    protected $model = Blok::class;

    public function definition(): array
    {
        static $nummer = 1;

        return [
            'toernooi_id' => Toernooi::factory(),
            'nummer' => $nummer++,
            'naam' => 'Blok ' . $nummer,
            'label' => fake()->optional()->randomElement(['Ochtend', 'Middag', 'Avond']),
            'weging_start' => '08:00',
            'weging_eind' => '09:00',
            'gewenst_wedstrijden' => null,
        ];
    }

    /**
     * Create a morning block.
     */
    public function ochtend(): static
    {
        return $this->state(fn (array $attributes) => [
            'label' => 'Ochtend',
            'weging_start' => '08:00',
            'weging_eind' => '09:00',
        ]);
    }

    /**
     * Create an afternoon block.
     */
    public function middag(): static
    {
        return $this->state(fn (array $attributes) => [
            'label' => 'Middag',
            'weging_start' => '12:00',
            'weging_eind' => '13:00',
        ]);
    }
}
