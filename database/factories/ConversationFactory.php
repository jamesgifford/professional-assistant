<?php

namespace Database\Factories;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_key' => fake()->uuid(),
            'channel' => fake()->randomElement(['api', 'sms', 'email']),
            'messages' => [],
            'provider_used' => null,
            'metadata' => null,
        ];
    }

    public function api(): static
    {
        return $this->state(fn () => ['channel' => 'api']);
    }

    public function sms(): static
    {
        return $this->state(fn () => [
            'channel' => 'sms',
            'session_key' => '+1'.fake()->numerify('##########'),
        ]);
    }

    public function email(): static
    {
        return $this->state(fn () => [
            'channel' => 'email',
            'session_key' => fake()->safeEmail(),
        ]);
    }

    /**
     * @param  array<array{role: string, content: string}>  $messages
     */
    public function withMessages(array $messages): static
    {
        return $this->state(fn () => ['messages' => $messages]);
    }
}
