<?php

use App\Ai\Agents\ProfessionalAssistant;
use App\Models\Conversation;

beforeEach(function () {
    ProfessionalAssistant::fake(['James is a Senior Software Engineer.']);
});

it('handles an incoming SMS and returns TwiML', function () {
    $response = $this->postJson('/webhook/sms', [
        'From' => '+15551234567',
        'Body' => 'Tell me about James',
        'MessageSid' => 'SM123456789',
    ]);

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'text/xml; charset=utf-8');
    expect($response->getContent())->toContain('<Response>');
    expect($response->getContent())->toContain('<Message>');

    $this->assertDatabaseHas('conversations', [
        'session_key' => '+15551234567',
        'channel' => 'sms',
    ]);
});

it('stores the Twilio message SID in metadata', function () {
    $this->postJson('/webhook/sms', [
        'From' => '+15559876543',
        'Body' => 'Hello',
        'MessageSid' => 'SM_TEST_SID',
    ]);

    $conversation = Conversation::where('session_key', '+15559876543')->first();
    expect($conversation->metadata['twilio_message_sid'])->toBe('SM_TEST_SID');
});

it('splits long responses into multiple SMS segments', function () {
    $longResponse = str_repeat('This is a test. ', 200);
    ProfessionalAssistant::fake([$longResponse]);

    $response = $this->postJson('/webhook/sms', [
        'From' => '+15551112222',
        'Body' => 'Tell me everything',
        'MessageSid' => 'SM_LONG',
    ]);

    $response->assertSuccessful();
    $content = $response->getContent();
    $messageCount = substr_count($content, '<Message>');
    expect($messageCount)->toBeGreaterThan(1);
});
