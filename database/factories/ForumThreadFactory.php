<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ForumThread>
 */
class ForumThreadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);

        $blocks = [
            [
                'type' => 'paragraph',
                'data' => [
                    'text' => fake()->paragraph(),
                ],
            ],
            [
                'type' => 'list',
                'data' => [
                    'style' => 'unordered',
                    'items' => fake()->sentences(2),
                ],
            ],
        ];

        $body = [
            'time' => now()->getTimestampMs(),
            'blocks' => $blocks,
            'version' => '2.30.7',
        ];

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(100, 999),
            'flair' => fake()->randomElement(['news', 'help', 'release', 'discussion', null]),
            'body' => json_encode($body),
            'replies_count' => 0,
            'last_posted_at' => now(),
            'pinned' => false,
            'locked' => false,
        ];
    }
}
