<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Mod>
 */
class ModFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        $ratingsCount = fake()->numberBetween(0, 250);
        $ratingValue = $ratingsCount > 0 ? fake()->randomFloat(2, 3, 5) : 0;

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(100, 999),
            'excerpt' => fake()->paragraph(),
            'description' => fake()->paragraphs(4, true),
            'version' => 'v' . fake()->randomFloat(1, 0.1, 2.5),
            'hero_image_path' => 'https://placehold.co/1280x720/ec4899/1f2937?text=GTA6+Mod',
            'download_url' => fake()->url(),
            'file_size' => fake()->randomFloat(2, 5, 2500),
            'rating' => $ratingValue,
            'ratings_count' => $ratingsCount,
            'likes' => fake()->numberBetween(0, 5000),
            'downloads' => fake()->numberBetween(100, 50000),
            'featured' => fake()->boolean(25),
            'status' => 'published',
            'published_at' => fake()->dateTimeBetween('-30 days'),
        ];
    }
}
