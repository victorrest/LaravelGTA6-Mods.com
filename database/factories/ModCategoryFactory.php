<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ModCategory>
 */
class ModCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'slug' => fn (array $attributes) => Str::slug($attributes['name']),
            'icon' => 'fa-solid fa-star',
            'description' => fake()->sentence(),
        ];
    }
}
