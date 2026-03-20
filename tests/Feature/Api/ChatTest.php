<?php

use App\Ai\Agents\ProfessionalAssistant;
use App\Models\Conversation;

beforeEach(function () {
    ProfessionalAssistant::fake(['James has 20 years of experience.']);
});

it('creates a new conversation and returns a response', function () {
    $response = $this->postJson('/api/chat', [
        'session_key' => 'test-session-1',
        'message' => 'Tell me about James.',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['response', 'session_key', 'provider']);

    expect($response->json('session_key'))->toBe('test-session-1');
    expect($response->json('response'))->toBeString()->not->toBeEmpty();

    $this->assertDatabaseHas('conversations', [
        'session_key' => 'test-session-1',
        'channel' => 'api',
    ]);
});

it('continues an existing conversation', function () {
    Conversation::factory()->api()->create([
        'session_key' => 'existing-session',
        'messages' => [
            ['role' => 'user', 'content' => 'Hi', 'timestamp' => now()->toIso8601String()],
            ['role' => 'assistant', 'content' => 'Hello!', 'timestamp' => now()->toIso8601String()],
        ],
    ]);

    $response = $this->postJson('/api/chat', [
        'session_key' => 'existing-session',
        'message' => 'What is his salary expectation?',
    ]);

    $response->assertSuccessful();

    $conversation = Conversation::where('session_key', 'existing-session')->first();
    expect($conversation->messages)->toHaveCount(4);
});

it('validates required fields', function () {
    $response = $this->postJson('/api/chat', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['session_key', 'message']);
});

it('validates session_key is required', function () {
    $response = $this->postJson('/api/chat', [
        'message' => 'Hello',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['session_key']);
});

it('validates message is required', function () {
    $response = $this->postJson('/api/chat', [
        'session_key' => 'test',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

it('returns a response from the test endpoint', function () {
    $response = $this->getJson('/api/chat/test');

    $response->assertSuccessful()
        ->assertJsonStructure(['response', 'session_key', 'provider']);
});
