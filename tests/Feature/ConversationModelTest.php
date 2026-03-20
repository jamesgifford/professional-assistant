<?php

use App\Models\Conversation;

it('creates a conversation with factory defaults', function () {
    $conversation = Conversation::factory()->api()->create();

    expect($conversation->channel)->toBe('api');
    expect($conversation->messages)->toBeEmpty();
});

it('appends messages to the conversation', function () {
    $conversation = Conversation::factory()->create();

    $conversation->appendMessage('user', 'Hello');
    $conversation->appendMessage('assistant', 'Hi there!');

    $messages = $conversation->messages()->oldest()->get();

    expect($messages)->toHaveCount(2);
    expect($messages[0]->role)->toBe('user');
    expect($messages[0]->content)->toBe('Hello');
    expect($messages[1]->role)->toBe('assistant');
    expect($messages[1]->content)->toBe('Hi there!');
    expect($messages[0]->created_at)->not->toBeNull();
});

it('casts metadata as array', function () {
    $conversation = Conversation::factory()->create([
        'metadata' => ['key' => 'value'],
    ]);

    $conversation->refresh();

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

it('deletes messages when conversation is deleted', function () {
    $conversation = Conversation::factory()->create();
    $conversation->appendMessage('user', 'Hello');
    $conversation->appendMessage('assistant', 'Hi!');

    expect($conversation->messages)->toHaveCount(2);

    $conversation->delete();

    $this->assertDatabaseCount('messages', 0);
});
