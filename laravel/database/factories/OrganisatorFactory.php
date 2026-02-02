<?php

namespace Database\Factories;

use App\Models\Organisator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organisator>
 */
class OrganisatorFactory extends Factory
{
    protected $model = Organisator::class;

    public function definition(): array
    {
        $naam = fake()->company();

        return [
            'naam' => $naam,
            'slug' => Str::slug($naam),
            'email' => fake()->unique()->safeEmail(),
            'telefoon' => fake()->phoneNumber(),
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_sitebeheerder' => false,
            'is_test' => false,
            'is_premium' => false,
            'kortingsregeling' => false,
            'kyc_compleet' => false,
        ];
    }

    /**
     * Indicate that the organisator is a site administrator.
     */
    public function sitebeheerder(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sitebeheerder' => true,
        ]);
    }

    /**
     * Indicate that the organisator has premium subscription.
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_premium' => true,
        ]);
    }

    /**
     * Indicate that the organisator has completed KYC.
     */
    public function kycCompleet(): static
    {
        return $this->state(fn (array $attributes) => [
            'kyc_compleet' => true,
            'kyc_ingevuld_op' => now(),
            'organisatie_naam' => fake()->company(),
            'kvk_nummer' => fake()->numerify('########'),
            'straat' => fake()->streetAddress(),
            'postcode' => fake()->postcode(),
            'plaats' => fake()->city(),
            'land' => 'Nederland',
            'contactpersoon' => fake()->name(),
            'factuur_email' => fake()->safeEmail(),
        ]);
    }

    /**
     * Indicate this is a test account.
     */
    public function test(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_test' => true,
        ]);
    }
}
