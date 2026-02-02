<?php

namespace Database\Factories;

use App\Models\Mat;
use App\Models\Toernooi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Mat>
 */
class MatFactory extends Factory
{
    protected $model = Mat::class;

    public function definition(): array
    {
        static $nummer = 1;

        return [
            'toernooi_id' => Toernooi::factory(),
            'nummer' => $nummer++,
            'naam' => 'Mat ' . $nummer,
        ];
    }
}
