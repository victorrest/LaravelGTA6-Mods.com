<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\EditorJsRenderer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NewsArticle>
 */
class NewsArticleFactory extends Factory
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
                'type' => 'header',
                'data' => [
                    'text' => fake()->sentence(6),
                    'level' => 2,
                ],
            ],
            [
                'type' => 'paragraph',
                'data' => [
                    'text' => fake()->paragraphs(2, true),
                ],
            ],
            [
                'type' => 'quote',
                'data' => [
                    'text' => fake()->sentence(),
                    'caption' => fake()->name(),
                    'alignment' => 'left',
                ],
            ],
        ];

        $body = [
            'time' => now()->getTimestampMs(),
            'blocks' => $blocks,
            'version' => '2.30.7',
        ];

        $bodyJson = json_encode($body);

        $excerpt = Str::limit(EditorJsRenderer::toPlainText($bodyJson, $body), 180);

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(100, 999),
            'excerpt' => $excerpt,
            'body' => $bodyJson,
            'published_at' => fake()->dateTimeBetween('-14 days'),
        ];
    }
}
