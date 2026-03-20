<?php

use Illuminate\Support\Facades\Cache;

it('returns health status for providers', function () {
    Cache::put('ai_health:anthropic', [
        'status' => 'up',
        'latency_ms' => 500,
        'last_checked' => now()->toIso8601String(),
    ], now()->addMinutes(5));

    Cache::put('ai_health:openai', [
        'status' => 'up',
        'latency_ms' => 300,
        'last_checked' => now()->toIso8601String(),
    ], now()->addMinutes(5));

    $response = $this->getJson('/api/health');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'providers' => [
                'anthropic' => ['status', 'latency_ms', 'last_checked'],
                'openai' => ['status', 'latency_ms', 'last_checked'],
            ],
            'active_provider',
        ]);
});

it('returns unknown status when no health data is cached', function () {
    Cache::flush();

    $response = $this->getJson('/api/health');

    $response->assertSuccessful();
    expect($response->json('providers.anthropic.status'))->toBe('unknown');
});

it('routes to fallback when primary is down', function () {
    Cache::put('ai_health:anthropic', [
        'status' => 'down',
        'latency_ms' => 0,
        'last_checked' => now()->toIso8601String(),
    ], now()->addMinutes(5));

    $response = $this->getJson('/api/health');

    $response->assertSuccessful();
    expect($response->json('active_provider'))->toBe('openai');
});
