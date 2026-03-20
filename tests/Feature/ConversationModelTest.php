<?php

use App\Models\Conversation;

it('creates a conversation with factory defaults', function () {
    $conversation = Conversation::factory()->api()->create();

    expect($conversation->channel)->toBe('api');
    expect($conversation->messages)->toBeArray()->toBeEmpty();
});

it('appends messages to the conversation', function () {
    $conversation = Conversation::factory()->create();

    $conversation->appendMessage('user', 'Hello');
    $conversation->appendMessage('assistant', 'Hi there!');

    expect($conversation->messages)->toHaveCount(2);
    expect($conversation->messages[0]['role'])->toBe('user');
    expect($conversation->messages[0]['content'])->toBe('Hello');
    expect($conversation->messages[1]['role'])->toBe('assistant');
    expect($conversation->messages[1]['content'])->toBe('Hi there!');
    expect($conversation->messages[0]['timestamp'])->not->toBeEmpty();
});

it('casts messages and metadata as arrays', function () {
    $conversation = Conversation::factory()->create([
        'messages' => [['role' => 'user', 'content' => 'test', 'timestamp' => now()->toIso8601String()]],
        'metadata' => ['key' => 'value'],
    ]);

    $conversation->refresh();

    expect($conversation->messages)->toBeArray();
    expect($conversation->metadata)->toBeArray();
    expect($conversation->metadata['key'])->toBe('value');
});

it('creates an sms conversation with factory state', function () {
    $conversation = Conversation::factory()->sms()->create();

    expect($conversation->channel)->toBe('sms');
    expect($conversation->session_key)->toStartWith('+1');
});

it('creates an email conversation with factory state', function () {
    $conversation = Conversation::factory()->email()->create();

    expect($conversation->channel)->toBe('email');
    expect($conversation->session_key)->toContain('@');
});
