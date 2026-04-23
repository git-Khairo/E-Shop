<?php

namespace Database\Factories;

use App\Models\categories;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<categories>
 */
class CategoriesFactory extends Factory
{
    protected $model = categories::class;

    public function definition(): array
    {
        return [
            'type' => $this->faker->unique()->word(),
        ];
    }
}
