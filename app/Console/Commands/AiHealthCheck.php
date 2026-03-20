<?php

namespace App\Console\Commands;

use App\Services\AiProviderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:health')]
#[Description('Check the health of configured AI providers')]
class AiHealthCheck extends Command
{
    public function handle(AiProviderService $aiService): int
    {
        $primary = config('ai.default', 'anthropic');
        $fallback = config('ai.fallback', 'openai');

        $this->info('Checking AI provider health...');
        $this->newLine();

        foreach ([$primary, $fallback] as $provider) {
            $this->info("Checking {$provider}...");
            $result = $aiService->checkProviderHealth($provider);

            $statusColor = $result['status'] === 'up' ? 'green' : 'red';
            $this->line("  Status: <fg={$statusColor}>{$result['status']}</>");
            $this->line("  Latency: {$result['latency_ms']}ms");
            $this->line("  Last checked: {$result['last_checked']}");
            $this->newLine();
        }

        $activeProvider = $aiService->getProviderOrder()[0];
        $this->info("Active provider: {$activeProvider}");

        return self::SUCCESS;
    }
}
