<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    public function __construct(
        private AiProviderService $aiService,
    ) {}

    public function __invoke(): JsonResponse
    {
        $primary = config('ai.default', 'anthropic');
        $fallback = config('ai.fallback', 'openai');

        $providers = [];
        foreach ([$primary, $fallback] as $provider) {
            $cached = Cache::get("ai_health:{$provider}");
            $providers[$provider] = $cached ?? [
                'status' => 'unknown',
                'latency_ms' => 0,
                'last_checked' => null,
            ];
        }

        $activeProvider = $this->aiService->getProviderOrder()[0];

        return response()->json([
            'providers' => $providers,
            'active_provider' => $activeProvider,
        ]);
    }
}
