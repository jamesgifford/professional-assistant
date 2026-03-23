<?php

use App\Ai\Agents\ProfessionalAssistant;
use App\Mail\ProfessionalAssistantReply;
use App\Models\Conversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Resend\Client as ResendClient;
use Resend\Emails\Receiving;

beforeEach(function () {
    ProfessionalAssistant::fake(['James is available for remote roles.']);
    Mail::fake();
});

function mockResendReceiving(string $text = '', ?string $html = null, array $headers = []): void
{
    $receiving = Mockery::mock();
    $receiving->shouldReceive('get')
        ->andReturn(new Receiving([
            'text' => $text,
            'html' => $html,
            'headers' => $headers,
        ]));

    $emails = Mockery::mock();
    $emails->receiving = $receiving;

    $client = Mockery::mock(ResendClient::class);
    $client->emails = $emails;

    app()->instance(ResendClient::class, $client);
}

function webhookPayload(array $overrides = []): array
{
    return [
        'type' => 'email.received',
        'data' => array_merge([
            'id' => 'em_test123',
            'email_id' => 'em_test123',
            'from' => 'recruiter@example.com',
            'to' => ['ask@jamesgifford.ai'],
            'subject' => 'Senior Engineer Position',
            'attachments' => [],
        ], $overrides),
    ];
}

// --- Core functionality ---

it('handles an incoming Resend email and sends a reply', function () {
    mockResendReceiving('Is James available for a new role?');

    $response = $this->postJson('/webhook/resend/inbound', webhookPayload());

    $response->assertSuccessful()
        ->assertJson(['status' => 'sent']);

    $this->assertDatabaseHas('conversations', [
        'session_key' => 'recruiter@example.com',
        'channel' => 'email',
    ]);

    Mail::assertSent(ProfessionalAssistantReply::class, function ($mail) {
        return $mail->hasTo('recruiter@example.com');
    });
});

it('stores channel and email metadata on individual messages', function () {
    mockResendReceiving('Tell me about James.');

    $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'hr@company.com',
        'subject' => 'Role Inquiry',
    ]));

    $conversation = Conversation::where('session_key', 'hr@company.com')->first();

    expect($conversation)->not->toBeNull();
    expect($conversation->channel)->toBe('email');

    $messages = $conversation->messages()->oldest()->get();

    expect($messages)->toHaveCount(2);
    expect($messages[0]->channel)->toBe('email');
    expect($messages[0]->metadata)->toMatchArray([
        'email' => 'hr@company.com',
        'subject' => 'Role Inquiry',
    ]);
    expect($messages[1]->channel)->toBe('email');
    expect($messages[1]->metadata)->toMatchArray([
        'email' => 'hr@company.com',
        'subject' => 'Role Inquiry',
    ]);
});

it('prepends Re: to the subject if not already present', function () {
    mockResendReceiving('Tell me about James.');

    $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'hr@company.com',
        'subject' => 'Engineering Role',
    ]));

    Mail::assertSent(ProfessionalAssistantReply::class, function ($mail) {
        return $mail->replySubject === 'Re: Engineering Role';
    });
});

it('does not double prepend Re:', function () {
    mockResendReceiving('Thanks for the info.');

    $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'hr@company.com',
        'subject' => 'Re: Engineering Role',
    ]));

    Mail::assertSent(ProfessionalAssistantReply::class, function ($mail) {
        return $mail->replySubject === 'Re: Engineering Role';
    });
});

it('passes the original message ID for threading', function () {
    mockResendReceiving('Hello');

    $this->postJson('/webhook/resend/inbound', webhookPayload([
        'id' => 'em_thread456',
        'email_id' => 'em_thread456',
        'from' => 'test@example.com',
    ]));

    Mail::assertSent(ProfessionalAssistantReply::class, function ($mail) {
        return $mail->originalMessageId === 'em_thread456';
    });
});

it('uses default subject when none is provided', function () {
    mockResendReceiving('Hello');

    $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'test@example.com',
        'subject' => '',
    ]));

    Mail::assertSent(ProfessionalAssistantReply::class, function ($mail) {
        return $mail->replySubject === 'Re: Professional Inquiry';
    });
});

it('falls back to HTML body when text is empty', function () {
    mockResendReceiving('', '<p>Tell me about James.</p>');

    $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'html@example.com',
    ]));

    $this->assertDatabaseHas('conversations', [
        'session_key' => 'html@example.com',
        'channel' => 'email',
    ]);

    Mail::assertSent(ProfessionalAssistantReply::class);
});

// --- Ignoring / filtering ---

it('ignores unsupported event types', function () {
    $response = $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.sent',
        'data' => ['id' => 'em_sent1'],
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'unsupported event type']);

    Mail::assertNothingSent();
});

it('ignores auto-reply emails from mailer-daemon', function () {
    $response = $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'mailer-daemon@example.com',
    ]));

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'auto-reply']);

    Mail::assertNothingSent();
});

it('ignores out of office replies', function () {
    $response = $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'person@example.com',
        'subject' => 'Out of Office Auto-Reply',
    ]));

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'auto-reply']);

    Mail::assertNothingSent();
});

it('ignores noreply senders in auto-reply check', function () {
    $response = $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'noreply@company.com',
    ]));

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'auto-reply']);

    Mail::assertNothingSent();
});

it('ignores emails from the inbound address to prevent loops', function () {
    config(['services.resend.inbound_address' => 'ask@jamesgifford.ai']);

    $response = $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'ask@jamesgifford.ai',
    ]));

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'auto-reply']);

    Mail::assertNothingSent();
});

it('ignores emails with empty body', function () {
    mockResendReceiving('');

    $response = $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'empty@example.com',
    ]));

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'empty body']);
});

it('ignores webhooks missing email_id', function () {
    $response = $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_noid',
            'from' => 'test@example.com',
            'to' => ['ask@jamesgifford.ai'],
            'subject' => 'No email_id',
        ],
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'missing email_id']);

    Mail::assertNothingSent();
});

// --- Bulk email filtering ---

it('silently ignores emails with List-Unsubscribe header', function () {
    mockResendReceiving('Buy now!', null, [
        ['name' => 'List-Unsubscribe', 'value' => '<mailto:unsubscribe@spam.com>'],
    ]);

    $response = $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'promos@store.com',
    ]));

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'bulk email']);

    Mail::assertNothingSent();
});

it('silently ignores emails with Precedence: bulk header', function () {
    mockResendReceiving('Newsletter content', null, [
        ['name' => 'Precedence', 'value' => 'bulk'],
    ]);

    $response = $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'info@company.com',
    ]));

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'bulk email']);

    Mail::assertNothingSent();
});

it('silently ignores emails with Precedence: list header', function () {
    mockResendReceiving('Mailing list post', null, [
        ['name' => 'Precedence', 'value' => 'list'],
    ]);

    $response = $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'list@community.org',
    ]));

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'bulk email']);

    Mail::assertNothingSent();
});

it('silently ignores emails from bulk sender prefixes', function (string $sender) {
    mockResendReceiving('Some notification');

    $response = $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => $sender,
    ]));

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'bulk email']);

    Mail::assertNothingSent();
})->with([
    'marketing@company.com',
    'newsletter@company.com',
    'notifications@company.com',
    'no-reply@company.com',
]);

// --- Signature and quote stripping ---

it('strips quoted reply content from email body', function () {
    mockResendReceiving("What about his salary?\n\nOn March 22, 2026 someone wrote:\n> Previous message content here");

    $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'quoter@example.com',
        'subject' => 'Re: Follow up',
    ]));

    $conversation = Conversation::where('session_key', 'quoter@example.com')->first();
    $userMessage = $conversation->messages()->where('role', 'user')->first();

    expect($userMessage->content)->toBe('What about his salary?');
});

it('strips Sent from my iPhone signatures', function () {
    mockResendReceiving("What is James's stack?\n\nSent from my iPhone");

    $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'mobile@example.com',
    ]));

    $conversation = Conversation::where('session_key', 'mobile@example.com')->first();
    $userMessage = $conversation->messages()->where('role', 'user')->first();

    expect($userMessage->content)->toBe("What is James's stack?");
});

it('strips standard email signature delimiter', function () {
    mockResendReceiving("Tell me about James.\n\n--\nJohn Doe\nRecruiter at Acme Inc.");

    $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'sig@example.com',
    ]));

    $conversation = Conversation::where('session_key', 'sig@example.com')->first();
    $userMessage = $conversation->messages()->where('role', 'user')->first();

    expect($userMessage->content)->toBe('Tell me about James.');
});

// --- Rate limiting ---

it('rate limits after exceeding hourly limit', function () {
    mockResendReceiving('Hello');

    Cache::put('email_rate:spammer@example.com:hour', 10, now()->addMinutes(60));

    $response = $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'spammer@example.com',
    ]));

    $response->assertSuccessful()
        ->assertJson(['status' => 'rate_limited']);

    Mail::assertSent(ProfessionalAssistantReply::class, function ($mail) {
        return $mail->hasTo('spammer@example.com')
            && str_contains($mail->responseText, 'conversation limit');
    });
});

it('rate limits after exceeding daily limit', function () {
    mockResendReceiving('Hello');

    Cache::put('email_rate:daily@example.com:day', 30, now()->addHours(24));

    $response = $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'daily@example.com',
    ]));

    $response->assertSuccessful()
        ->assertJson(['status' => 'rate_limited']);

    Mail::assertSent(ProfessionalAssistantReply::class, function ($mail) {
        return $mail->hasTo('daily@example.com')
            && str_contains($mail->responseText, 'conversation limit');
    });
});

it('increments rate limit counters on successful messages', function () {
    mockResendReceiving('Hello');

    $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'counter@example.com',
    ]));

    expect(Cache::get('email_rate:counter@example.com:hour'))->toBe(1);
    expect(Cache::get('email_rate:counter@example.com:day'))->toBe(1);
});

it('does not call AI provider when rate limited', function () {
    mockResendReceiving('Hello');

    Cache::put('email_rate:limited@example.com:hour', 10, now()->addMinutes(60));

    $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'limited@example.com',
    ]));

    $conversation = Conversation::where('session_key', 'limited@example.com')->first();
    $messages = $conversation->messages()->oldest()->get();

    expect($messages)->toHaveCount(2);
    expect($messages[0]->content)->toBe('[rate limited]');
    expect($messages[1]->metadata)->toMatchArray(['rate_limited' => true]);
});

// --- Attachment awareness ---

it('prepends attachment notice when email has attachments', function () {
    mockResendReceiving('Here is my resume.');

    $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'attached@example.com',
        'attachments' => [
            ['filename' => 'resume.pdf', 'content_type' => 'application/pdf'],
        ],
    ]));

    $conversation = Conversation::where('session_key', 'attached@example.com')->first();
    $userMessage = $conversation->messages()->where('role', 'user')->first();

    expect($userMessage->content)->toContain('[Note: The sender included file attachments');
    expect($userMessage->content)->toContain('Here is my resume.');
    expect($userMessage->metadata)->toMatchArray([
        'has_attachments' => true,
        'attachment_count' => 1,
    ]);
});

it('does not prepend attachment notice when no attachments', function () {
    mockResendReceiving('Just a plain message.');

    $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'plain@example.com',
        'attachments' => [],
    ]));

    $conversation = Conversation::where('session_key', 'plain@example.com')->first();
    $userMessage = $conversation->messages()->where('role', 'user')->first();

    expect($userMessage->content)->toBe('Just a plain message.');
    expect($userMessage->metadata)->not->toHaveKey('has_attachments');
});

// --- Subject in conversation metadata ---

it('stores subject, message_id, and thread_id in conversation metadata', function () {
    mockResendReceiving('Hello');

    $this->postJson('/webhook/resend/inbound', webhookPayload([
        'id' => 'em_meta1',
        'email_id' => 'em_meta1',
        'from' => 'meta@example.com',
        'subject' => 'Job Opportunity',
        'thread_id' => 'thread_abc',
    ]));

    $conversation = Conversation::where('session_key', 'meta@example.com')->first();

    expect($conversation->metadata)->toMatchArray([
        'email' => 'meta@example.com',
        'subject' => 'Job Opportunity',
        'message_id' => 'em_meta1',
        'thread_id' => 'thread_abc',
    ]);
});

it('updates subject in conversation metadata on follow-up with different subject', function () {
    mockResendReceiving('First message');

    $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'followup@example.com',
        'subject' => 'Original Subject',
    ]));

    mockResendReceiving('Follow-up message');

    $this->postJson('/webhook/resend/inbound', webhookPayload([
        'id' => 'em_followup2',
        'email_id' => 'em_followup2',
        'from' => 'followup@example.com',
        'subject' => 'New Subject',
    ]));

    $conversation = Conversation::where('session_key', 'followup@example.com')->first();

    expect($conversation->metadata['subject'])->toBe('New Subject');
});

// --- Whitelist and blacklist ---

it('rejects blacklisted senders without processing', function () {
    config(['services.resend.blacklist' => ['banned@example.com']]);

    $response = $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'banned@example.com',
    ]));

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'blacklisted']);

    Mail::assertNothingSent();
    $this->assertDatabaseMissing('conversations', ['session_key' => 'banned@example.com']);
});

it('blacklist check is case-insensitive', function () {
    config(['services.resend.blacklist' => ['Banned@Example.com']]);

    $response = $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'banned@example.com',
    ]));

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'blacklisted']);

    Mail::assertNothingSent();
});

it('whitelisted senders bypass rate limiting', function () {
    config(['services.resend.whitelist' => ['vip@example.com']]);
    mockResendReceiving('Hello');

    Cache::put('email_rate:vip@example.com:hour', 10, now()->addMinutes(60));
    Cache::put('email_rate:vip@example.com:day', 30, now()->addHours(24));

    $response = $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'vip@example.com',
    ]));

    $response->assertSuccessful()
        ->assertJson(['status' => 'sent']);
});

it('whitelisted senders bypass bulk email filtering', function () {
    config(['services.resend.whitelist' => ['newsletter@partner.com']]);
    mockResendReceiving('Important update', null, [
        ['name' => 'List-Unsubscribe', 'value' => '<mailto:unsub@partner.com>'],
    ]);

    $response = $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'newsletter@partner.com',
    ]));

    $response->assertSuccessful()
        ->assertJson(['status' => 'sent']);
});

it('whitelist check is case-insensitive', function () {
    config(['services.resend.whitelist' => ['VIP@Example.com']]);
    mockResendReceiving('Hello');

    $response = $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'vip@example.com',
    ]));

    $response->assertSuccessful()
        ->assertJson(['status' => 'sent']);
});

it('blacklist takes precedence over whitelist', function () {
    config([
        'services.resend.whitelist' => ['conflict@example.com'],
        'services.resend.blacklist' => ['conflict@example.com'],
    ]);

    $response = $this->postJson('/webhook/resend/inbound', webhookPayload([
        'from' => 'conflict@example.com',
    ]));

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'blacklisted']);

    Mail::assertNothingSent();
});
