<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Organisator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Club>
 */
class ClubFactory extends Factory
{
    protected $model = Club::class;

    public function definition(): array
    {
        $prefixes = ['Judo', 'Judovereniging', 'JV', 'Judoclub', 'Sportvereniging'];

        return [
            'organisator_id' => Organisator::factory(),
            'naam' => fake()->randomElement($prefixes) . ' ' . fake()->unique()->city(),
            'email' => fake()->unique()->safeEmail(),
            'email2' => fake()->optional(0.3)->safeEmail(),
            'contact_naam' => fake()->name(),
            'telefoon' => fake()->phoneNumber(),
            'plaats' => fake()->city(),
            'website' => fake()->optional(0.5)->url(),
        ];
    }

    /**
     * Create a club without contact details.
     */
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'contact_naam' => null,
            'telefoon' => null,
        ]);
    }
}
