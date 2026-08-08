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
            'provider' => $this->faker->word(),
            'handle' => $this->faker->word(),
            'display_name' => $this->faker->words(2, true),
            'active' => true,
            'last_fetched_at' => null,
            'visible' => true,
            'topic' => $this->faker->word(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'active' => false,
        ]);
    }
}
