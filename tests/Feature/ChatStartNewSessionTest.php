<?php

use App\Livewire\Chat;
use App\Models\Conversation;
use App\Services\AiProviderService;
use Livewire\Livewire;

it('resets messages to just a greeting on new session', function () {
    $this->mock(AiProviderService::class)
        ->shouldReceive('chat')
        ->once()
        ->andReturn(['response' => 'Hello! How can I help?', 'provider' => 'anthropic']);

    $component = Livewire::test(Chat::class)
        ->set('input', 'Hello')
        ->call('sendMessage')
        ->assertCount('messages', 3);

    $component->call('startNewSession')
        ->assertCount('messages', 1)
        ->assertSet('input', '')
        ->assertSet('isProcessing', false)
        ->assertDispatched('session-reset');
});

it('picks a greeting from configured greetings on new session', function () {
    $greetings = config('chat.greetings');

    $component = Livewire::test(Chat::class)
        ->call('startNewSession');

    $messages = $component->get('messages');

    expect($messages)->toHaveCount(1);
    expect($messages[0]['role'])->toBe('assistant');
    expect($messages[0]['content'])->toBeIn($greetings);
});

it('regenerates session so subsequent messages create a new conversation', function () {
    $this->mock(AiProviderService::class)
        ->shouldReceive('chat')
        ->twice()
        ->andReturn(['response' => 'Reply', 'provider' => 'anthropic']);

    $component = Livewire::test(Chat::class)
        ->set('input', 'First conversation')
        ->call('sendMessage');

    $firstSessionKey = 'web-'.session()->getId();

    $component->call('startNewSession')
        ->set('input', 'Second conversation')
        ->call('sendMessage');

    $secondSessionKey = 'web-'.session()->getId();

    expect($firstSessionKey)->not->toBe($secondSessionKey);
    expect(Conversation::where('session_key', $firstSessionKey)->exists())->toBeTrue();
    expect(Conversation::where('session_key', $secondSessionKey)->exists())->toBeTrue();
});
