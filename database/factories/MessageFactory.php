<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'role' => fake()->randomElement(['user', 'assistant']),
            'content' => fake()->sentence(),
        ];
    }

    public function user(?string $content = null): static
    {
        return $this->state(fn () => [
            'role' => 'user',
            'content' => $content ?? fake()->sentence(),
        ]);
    }

    public function assistant(?string $content = null): static
    {
        return $this->state(fn () => [
            'role' => 'assistant',
            'content' => $content ?? fake()->sentence(),
        ]);
    }
}
