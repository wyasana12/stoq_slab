<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => $this->faker->unique()->bothify('PROD-####'),
            'name' => $this->faker->word(3, true),
            'category_id' => Category::all()->random()->id,
            'unit_id' => Unit::all()->random()->id,
            'min_quantity' => $this->faker->numberBetween(5, 20),
            'return_limit_days' => $this->faker->randomElement([7, 14, 30, null]),
        ];
    }
}
