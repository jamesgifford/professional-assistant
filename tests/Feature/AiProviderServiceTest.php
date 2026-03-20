<?php

use App\Services\AiProviderService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

it('excludes providers without credentials', function () {
    Config::set('ai.providers.anthropic.key', 'sk-ant-test');
    Config::set('ai.providers.openai.key', null);

    $service = new AiProviderService;

    expect($service->getProviderOrder())->toBe(['anthropic']);
});

it('includes both providers when both have credentials', function () {
    Config::set('ai.providers.anthropic.key', 'sk-ant-test');
    Config::set('ai.providers.openai.key', 'sk-openai-test');

    $service = new AiProviderService;

    expect($service->getProviderOrder())->toBe(['anthropic', 'openai']);
});

it('throws when no providers have credentials', function () {
    Config::set('ai.providers.anthropic.key', null);
    Config::set('ai.providers.openai.key', null);

    $service = new AiProviderService;

    $service->getProviderOrder();
})->throws(RuntimeException::class, 'No AI providers have API keys configured');

it('respects health status when filtering providers', function () {
    Config::set('ai.providers.anthropic.key', 'sk-ant-test');
    Config::set('ai.providers.openai.key', 'sk-openai-test');

    Cache::put('ai_health:anthropic', ['status' => 'down'], now()->addMinutes(5));

    $service = new AiProviderService;

    expect($service->getProviderOrder())->toBe(['openai', 'anthropic']);
});

it('skips unhealthy provider if it has no credentials', function () {
    Config::set('ai.providers.anthropic.key', null);
    Config::set('ai.providers.openai.key', 'sk-openai-test');

    Cache::put('ai_health:anthropic', ['status' => 'down'], now()->addMinutes(5));

    $service = new AiProviderService;

    expect($service->getProviderOrder())->toBe(['openai']);
});
