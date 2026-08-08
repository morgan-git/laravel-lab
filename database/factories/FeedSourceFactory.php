<?php

namespace Database\Factories;

use App\Models\FeedSource;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeedSourceFactory extends Factory
{
    protected $model = FeedSource::class;

    public function definition(): array
    {
        return [
            'provider' => $this->faker->word(),
            'handle' => $this->faker->word(),
            'display_name' => $this->faker->words(2, true),
            'active' => true,
            'last_fetched_at' => null,
            'visible' => true,
            'topic_id' => Topic::factory(), // Automatically creates a topic in memory
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'active' => false,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => [
            'visible' => false,
        ]);
    }

    public function topic(string $name): static
    {
        return $this->state(fn () => [
            'topic_id' => Topic::firstOrCreate(['name' => $name])->id,
        ]);
    }
}
