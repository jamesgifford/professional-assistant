<?php

use App\Ai\Agents\ProfessionalAssistant;
use App\Models\Conversation;
use Illuminate\Support\Facades\Cache;

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

it('truncates overly long AI responses with suffix', function () {
    // 3200 chars exceeds the 1600 max, should be truncated
    $longResponse = str_repeat('This is a sentence. ', 200);
    ProfessionalAssistant::fake([$longResponse]);

    $response = $this->postJson('/webhook/sms', [
        'From' => '+15551112222',
        'Body' => 'Tell me everything',
        'MessageSid' => 'SM_LONG',
    ]);

    $response->assertSuccessful();
    $content = $response->getContent();

    preg_match('/<Message>(.*?)<\/Message>/s', $content, $matches);
    $messageContent = html_entity_decode($matches[1] ?? '');

    expect(mb_strlen($messageContent))->toBeLessThanOrEqual(1600);
    expect($messageContent)->toContain('more at jamesgifford.ai');
});

it('stores geographic metadata from Twilio', function () {
    $this->postJson('/webhook/sms', [
        'From' => '+15551234567',
        'Body' => 'Hello',
        'MessageSid' => 'SM_GEO',
        'FromCity' => 'Portland',
        'FromState' => 'OR',
        'FromCountry' => 'US',
    ]);

    $conversation = Conversation::where('session_key', '+15551234567')->first();
    expect($conversation->metadata['city'])->toBe('Portland');
    expect($conversation->metadata['state'])->toBe('OR');
    expect($conversation->metadata['country'])->toBe('US');
});

it('sets channel to sms on conversation and messages', function () {
    $this->postJson('/webhook/sms', [
        'From' => '+15553334444',
        'Body' => 'Hi there',
        'MessageSid' => 'SM_CHANNEL',
    ]);

    $conversation = Conversation::where('session_key', '+15553334444')->first();
    expect($conversation->channel)->toBe('sms');
    expect($conversation->messages()->where('role', 'user')->first()->channel)->toBe('sms');
    expect($conversation->messages()->where('role', 'assistant')->first()->channel)->toBe('sms');
});

it('rate limits after exceeding hourly limit', function () {
    $phone = '+15559999999';

    // Exhaust the hourly limit
    for ($i = 0; $i < 10; $i++) {
        Cache::put("sms_rate:{$phone}:hour", $i + 1, now()->addMinutes(60));
    }

    $response = $this->postJson('/webhook/sms', [
        'From' => $phone,
        'Body' => 'This should be rate limited',
        'MessageSid' => 'SM_RATE',
    ]);

    $response->assertSuccessful();
    expect($response->getContent())->toContain('conversation limit');
});

it('rate limits after exceeding daily limit', function () {
    $phone = '+15558888888';

    Cache::put("sms_rate:{$phone}:day", 20, now()->addHours(24));

    $response = $this->postJson('/webhook/sms', [
        'From' => $phone,
        'Body' => 'This should be rate limited',
        'MessageSid' => 'SM_RATE_DAY',
    ]);

    $response->assertSuccessful();
    expect($response->getContent())->toContain('conversation limit');
});

it('allowlisted senders bypass rate limiting', function () {
    $phone = '+15557777777';

    config(['services.twilio.allowlist' => [$phone]]);

    // Exhaust the hourly limit
    Cache::put("sms_rate:{$phone}:hour", 10, now()->addMinutes(60));

    $response = $this->postJson('/webhook/sms', [
        'From' => $phone,
        'Body' => 'Hello',
        'MessageSid' => 'SM_ALLOW_RATE',
    ]);

    $response->assertSuccessful();
    expect($response->getContent())->not->toContain('conversation limit');
});

it('rejects blocklisted senders with empty response', function () {
    config(['services.twilio.blocklist' => ['+15556666666']]);

    $response = $this->postJson('/webhook/sms', [
        'From' => '+15556666666',
        'Body' => 'Hello',
        'MessageSid' => 'SM_BLOCK',
    ]);

    $response->assertSuccessful();
    expect($response->getContent())->toBe('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
});

it('rejects non-allowlisted senders when allowlist is configured', function () {
    config(['services.twilio.allowlist' => ['+15551111111']]);

    $response = $this->postJson('/webhook/sms', [
        'From' => '+15552222222',
        'Body' => 'Hello',
        'MessageSid' => 'SM_NOT_ALLOWED',
    ]);

    $response->assertSuccessful();
    expect($response->getContent())->toBe('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
});

it('allows all senders when allowlist is empty', function () {
    config(['services.twilio.allowlist' => []]);

    $response = $this->postJson('/webhook/sms', [
        'From' => '+15553333333',
        'Body' => 'Hello',
        'MessageSid' => 'SM_OPEN',
    ]);

    $response->assertSuccessful();
    expect($response->getContent())->toContain('<Message>');
});

it('handles STOP keyword and adds to blocklist', function () {
    $response = $this->postJson('/webhook/sms', [
        'From' => '+15554444444',
        'Body' => 'STOP',
        'MessageSid' => 'SM_STOP',
    ]);

    $response->assertSuccessful();
    expect($response->getContent())->toContain('unsubscribed');
    expect(Cache::get('sms_blocklist', []))->toContain('+15554444444');
});

it('handles START keyword and removes from blocklist', function () {
    Cache::forever('sms_blocklist', ['+15554444444']);

    $response = $this->postJson('/webhook/sms', [
        'From' => '+15554444444',
        'Body' => 'START',
        'MessageSid' => 'SM_START',
    ]);

    $response->assertSuccessful();
    expect($response->getContent())->toContain('resubscribed');
    expect(Cache::get('sms_blocklist', []))->not->toContain('+15554444444');
});

it('handles HELP keyword', function () {
    $response = $this->postJson('/webhook/sms', [
        'From' => '+15555555555',
        'Body' => 'help',
        'MessageSid' => 'SM_HELP',
    ]);

    $response->assertSuccessful();
    expect($response->getContent())->toContain('professional assistant');
    expect($response->getContent())->toContain('STOP');
});

it('handles all opt-out keyword variants', function () {
    $keywords = ['stop', 'stopall', 'unsubscribe', 'cancel', 'end'];

    foreach ($keywords as $index => $keyword) {
        Cache::forget('sms_blocklist');

        $response = $this->postJson('/webhook/sms', [
            'From' => "+1555000000{$index}",
            'Body' => strtoupper($keyword),
            'MessageSid' => "SM_OPT_{$index}",
        ]);

        $response->assertSuccessful();
        expect($response->getContent())->toContain('unsubscribed');
    }
});

it('returns empty response for empty messages without media', function () {
    $response = $this->postJson('/webhook/sms', [
        'From' => '+15551234567',
        'Body' => '',
        'MessageSid' => 'SM_EMPTY',
        'NumMedia' => '0',
    ]);

    $response->assertSuccessful();
    expect($response->getContent())->toBe('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
});

it('returns empty response for whitespace-only messages', function () {
    $response = $this->postJson('/webhook/sms', [
        'From' => '+15551234567',
        'Body' => '   ',
        'MessageSid' => 'SM_WHITESPACE',
        'NumMedia' => '0',
    ]);

    $response->assertSuccessful();
    expect($response->getContent())->toBe('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
});

it('handles MMS with media attachments', function () {
    $response = $this->postJson('/webhook/sms', [
        'From' => '+15551234567',
        'Body' => 'Check out this image',
        'MessageSid' => 'SM_MMS',
        'NumMedia' => '1',
        'MediaContentType0' => 'image/jpeg',
        'MediaUrl0' => 'https://api.twilio.com/media/test.jpg',
    ]);

    $response->assertSuccessful();
    expect($response->getContent())->toContain('<Message>');

    $conversation = Conversation::where('session_key', '+15551234567')->first();
    expect($conversation->metadata['media_count'])->toBe(1);
    expect($conversation->metadata['media_types'])->toBe(['image/jpeg']);
});

it('handles MMS-only messages with no text body', function () {
    $response = $this->postJson('/webhook/sms', [
        'From' => '+15551234567',
        'Body' => '',
        'MessageSid' => 'SM_MMS_ONLY',
        'NumMedia' => '1',
        'MediaContentType0' => 'image/png',
        'MediaUrl0' => 'https://api.twilio.com/media/test.png',
    ]);

    $response->assertSuccessful();
    expect($response->getContent())->toContain('<Message>');
});

it('ignores messages from own Twilio number', function () {
    config(['services.twilio.phone_number' => '+15550000000']);

    $response = $this->postJson('/webhook/sms', [
        'From' => '+15550000000',
        'Body' => 'Hello',
        'MessageSid' => 'SM_LOOP',
    ]);

    $response->assertSuccessful();
    expect($response->getContent())->toBe('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
});

it('returns valid TwiML on AI provider failure', function () {
    ProfessionalAssistant::fake(fn () => throw new RuntimeException('Unexpected error'));

    $response = $this->postJson('/webhook/sms', [
        'From' => '+15551234567',
        'Body' => 'Hello',
        'MessageSid' => 'SM_ERROR',
    ]);

    $response->assertSuccessful();
    $content = $response->getContent();
    expect($content)->toContain('<?xml version="1.0" encoding="UTF-8"?>');
    expect($content)->toContain('<Response>');
    expect($content)->toContain('technical difficulties');
});

it('includes first message hint for new conversations', function () {
    $response = $this->postJson('/webhook/sms', [
        'From' => '+15559998888',
        'Body' => 'Who is James?',
        'MessageSid' => 'SM_FIRST',
    ]);

    $response->assertSuccessful();

    $conversation = Conversation::where('session_key', '+15559998888')->first();
    $userMessage = $conversation->messages()->where('role', 'user')->first();

    // The enriched body should contain the first message hint
    expect($userMessage->content)->toContain('first message from this sender');
});

it('does not include first message hint for existing conversations', function () {
    // Create existing conversation with a message
    $conversation = Conversation::create([
        'session_key' => '+15557776666',
        'channel' => 'sms',
        'metadata' => ['phone_number' => '+15557776666'],
    ]);
    $conversation->appendMessage('user', 'Previous message');
    $conversation->appendMessage('assistant', 'Previous response');

    $response = $this->postJson('/webhook/sms', [
        'From' => '+15557776666',
        'Body' => 'Follow up question',
        'MessageSid' => 'SM_FOLLOWUP',
    ]);

    $response->assertSuccessful();

    $latestUserMessage = $conversation->messages()->where('role', 'user')->latest()->first();
    expect($latestUserMessage->content)->not->toContain('first message from this sender');
});

it('records rate limited messages in conversation', function () {
    $phone = '+15550001111';

    Cache::put("sms_rate:{$phone}:hour", 10, now()->addMinutes(60));

    $this->postJson('/webhook/sms', [
        'From' => $phone,
        'Body' => 'Should be rate limited',
        'MessageSid' => 'SM_RATE_RECORD',
    ]);

    $conversation = Conversation::where('session_key', $phone)->first();
    $assistantMessage = $conversation->messages()->where('role', 'assistant')->first();

    expect($assistantMessage->metadata['rate_limited'])->toBeTrue();
});

it('rejects dynamically blocklisted senders via STOP', function () {
    // First, opt out
    $this->postJson('/webhook/sms', [
        'From' => '+15552223333',
        'Body' => 'STOP',
        'MessageSid' => 'SM_STOP_THEN_MSG',
    ]);

    // Now try to send a message
    $response = $this->postJson('/webhook/sms', [
        'From' => '+15552223333',
        'Body' => 'Hello again',
        'MessageSid' => 'SM_AFTER_STOP',
    ]);

    $response->assertSuccessful();
    expect($response->getContent())->toBe('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
});

it('includes SMS context hint in messages sent to AI', function () {
    $this->postJson('/webhook/sms', [
        'From' => '+15554443333',
        'Body' => 'Tell me about James',
        'MessageSid' => 'SM_HINT',
    ]);

    $conversation = Conversation::where('session_key', '+15554443333')->first();
    $userMessage = $conversation->messages()->where('role', 'user')->first();

    expect($userMessage->content)->toContain('conversation is happening over SMS');
    expect($userMessage->content)->toContain('under 320 characters');
});
