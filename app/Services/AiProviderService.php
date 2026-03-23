<?php

namespace App\Services;

use App\Ai\Agents\ProfessionalAssistant;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;

class AiProviderService
{
    /**
     * Determine the provider order based on health status.
     *
     * @return string[]
     */
    public function getProviderOrder(): array
    {
        $primary = config('ai.default', 'anthropic');
        $fallback = config('ai.fallback', 'openai');

        $primaryHealth = Cache::get("ai_health:{$primary}");

        if ($primaryHealth && ($primaryHealth['status'] ?? 'unknown') === 'down') {
            Log::info("Primary provider [{$primary}] is down, routing to fallback [{$fallback}]");

            $providers = [$fallback, $primary];
        } else {
            $providers = [$primary, $fallback];
        }

        $configured = array_values(array_filter($providers, fn (string $provider) => $this->hasCredentials($provider)));

        if (empty($configured)) {
            throw new \RuntimeException('No AI providers have API keys configured. Set at least one provider key in your .env file.');
        }

        return $configured;
    }

    /**
     * Check if a provider has credentials configured.
     */
    private function hasCredentials(string $provider): bool
    {
        $key = config("ai.providers.{$provider}.key");

        return filled($key);
    }

    /**
     * Send a message in a conversation and return the AI response.
     *
     * @return array{response: string, provider: string}
     */
    /**
     * @param  array<string, mixed>|null  $messageMetadata
     * @return array{response: string, provider: string}
     */
    public function chat(Conversation $conversation, string $message, ?array $messageMetadata = null): array
    {
        $previousMessages = $conversation->messages()
            ->oldest()
            ->get()
            ->map(fn (Message $msg) => match ($msg->role) {
                'assistant' => new AssistantMessage($msg->content),
                default => new UserMessage($msg->content),
            })
            ->all();

        $conversation->appendMessage('user', $message, $messageMetadata);

        $providers = $this->getProviderOrder();

        $agent = new ProfessionalAssistant($previousMessages);

        $response = $agent->prompt($message, provider: $providers);

        $responseText = (string) $response;
        $providerUsed = $providers[0];

        $toolNames = $response->toolCalls
            ->map(fn ($toolCall) => $toolCall->name)
            ->unique()
            ->values()
            ->all();

        $assistantMetadata = $messageMetadata ?? [];

        if (! empty($toolNames)) {
            $assistantMetadata['tools'] = $toolNames;
        }

        $conversation->appendMessage('assistant', $responseText, $assistantMetadata ?: null);
        $conversation->update(['provider_used' => $providerUsed]);

        Log::info('AI response generated', [
            'session_key' => $conversation->session_key,
            'provider' => $providerUsed,
            'channel' => $conversation->channel,
        ]);

        return [
            'response' => $responseText,
            'provider' => $providerUsed,
        ];
    }

    /**
     * Check health of a specific provider.
     *
     * @return array{status: string, latency_ms: int, last_checked: string}
     */
    public function checkProviderHealth(string $provider): array
    {
        try {
            $start = microtime(true);

            $agent = new ProfessionalAssistant;
            $response = $agent->prompt('Respond with OK', provider: $provider, model: $this->getHealthCheckModel($provider));

            $latency = (int) round((microtime(true) - $start) * 1000);

            $result = [
                'status' => 'up',
                'latency_ms' => $latency,
                'last_checked' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            Log::warning("AI provider [{$provider}] health check failed: {$e->getMessage()}");

            $result = [
                'status' => 'down',
                'latency_ms' => 0,
                'last_checked' => now()->toIso8601String(),
            ];
        }

        Cache::put("ai_health:{$provider}", $result, now()->addMinutes(5));

        return $result;
    }

    private function getHealthCheckModel(string $provider): string
    {
        return match ($provider) {
            'anthropic' => 'claude-sonnet-4-20250514',
            'openai' => 'gpt-4',
            default => 'gpt-4',
        };
    }
}
