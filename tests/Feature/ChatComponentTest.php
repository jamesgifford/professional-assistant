<?php

use App\Livewire\Chat;
use App\Models\Conversation;
use App\Services\AiProviderService;
use Livewire\Livewire;

it('renders the chat component', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeLivewire(Chat::class);
});

it('sends a message and receives a response', function () {
    $this->mock(AiProviderService::class)
        ->shouldReceive('chat')
        ->once()
        ->andReturn(['response' => 'Hello! How can I help?', 'provider' => 'anthropic']);

    Livewire::test(Chat::class)
        ->set('input', 'Hello')
        ->call('sendMessage')
        ->assertSet('input', '')
        ->assertCount('messages', 3);
});

it('validates that input is required', function () {
    Livewire::test(Chat::class)
        ->set('input', '')
        ->call('sendMessage')
        ->assertHasErrors(['input' => 'required']);
});

it('loads existing conversation messages on mount', function () {
    $sessionKey = 'web-'.session()->getId();

    $conversation = Conversation::factory()->create([
        'session_key' => $sessionKey,
        'channel' => 'web',
    ]);

    $conversation->appendMessage('user', 'Hi');
    $conversation->appendMessage('assistant', 'Hello!');

    Livewire::test(Chat::class)
        ->assertCount('messages', 3);
});

it('handles ai service errors gracefully', function () {
    $this->mock(AiProviderService::class)
        ->shouldReceive('chat')
        ->once()
        ->andReturnUsing(function (Conversation $conversation, string $message) {
            $conversation->appendMessage('user', $message);
            throw new RuntimeException('Provider unavailable');
        });

    Livewire::test(Chat::class)
        ->set('input', 'Hello')
        ->call('sendMessage')
        ->assertCount('messages', 3)
        ->assertSet('isProcessing', false);

    $conversation = Conversation::where('channel', 'web')->latest()->first();

    expect($conversation)->not->toBeNull();

    $messages = $conversation->messages()->oldest()->get();

    expect($messages)->toHaveCount(2);
    expect($messages[0]->role)->toBe('user');
    expect($messages[0]->content)->toBe('Hello');
    expect($messages[1]->role)->toBe('assistant');
    expect($messages[1]->content)->toBe('Sorry, something went wrong. Please try again.');
    expect($messages[1]->metadata)->toBe([
        'error' => true,
        'exception' => 'RuntimeException',
        'exception_message' => 'Provider unavailable',
    ]);
});
