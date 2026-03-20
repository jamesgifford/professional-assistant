<?php

namespace App\Services;

use App\Ai\Agents\ProfessionalAssistant;
use App\Models\Conversation;
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

            return [$fallback, $primary];
        }

        return [$primary, $fallback];
    }

    /**
     * Send a message in a conversation and return the AI response.
     *
     * @return array{response: string, provider: string}
     */
    public function chat(Conversation $conversation, string $message): array
    {
        $conversation->appendMessage('user', $message);

        $messages = collect($conversation->messages ?? [])
            ->slice(0, -1)
            ->map(fn (array $msg) => match ($msg['role']) {
                'assistant' => new AssistantMessage($msg['content']),
                default => new UserMessage($msg['content']),
            })
            ->values()
            ->all();

        $providers = $this->getProviderOrder();

        $agent = new ProfessionalAssistant($messages);

        $response = $agent->prompt($message, provider: $providers);

        $responseText = (string) $response;
        $providerUsed = $providers[0];

        $conversation->appendMessage('assistant', $responseText);
        $conversation->provider_used = $providerUsed;
        $conversation->save();

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
