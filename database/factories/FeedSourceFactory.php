<?php

namespace Database\Factories;

use App\Models\FeedSource;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeedSourceFactory extends Factory
{
    protected $model = FeedSource::class;

    public function definition(): array
    {
        return [
            'provider' => 'reddit',
            'handle' => $this->faker->randomElement(['foodporn', 'memes']),
            'display_name' => $this->faker->words(2, true),
            'active' => true,
            'last_fetched_at' => null,
            'visible' => true,
            'topic' => 'foodporn',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }

    public function reddit(string $topic): static
    {
        return $this->state(fn () => [
            'provider' => 'reddit',
            'topic' => $topic,
            'display_name' => "r/{$topic}",
        ]);
    }
}
