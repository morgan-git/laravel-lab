<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FeedPost;
use App\Models\FeedSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedPost>
 */
class FeedPostFactory extends Factory
{
    protected $model = FeedPost::class;

    public function definition(): array
    {
        return [
            'feed_source_id' => FeedSource::factory(),
            'external_id' => fake()->unique()->numerify('8##################'),
            'title' => fake()->sentence(),
            'url' => fake()->url(),
            'author' => fake()->userName(),
            'image_url' => fake()->imageUrl(),
            'content' => fake()->paragraph(),
            'posted_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
