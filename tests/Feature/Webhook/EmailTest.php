<?php

use App\Ai\Agents\ProfessionalAssistant;
use App\Mail\ProfessionalAssistantReply;
use App\Models\Conversation;
use Illuminate\Support\Facades\Mail;
use Resend\Client as ResendClient;
use Resend\Emails\Receiving;

beforeEach(function () {
    ProfessionalAssistant::fake(['James is available for remote roles.']);
    Mail::fake();
});

function mockResendReceiving(string $text = '', ?string $html = null): void
{
    $receiving = Mockery::mock();
    $receiving->shouldReceive('get')
        ->andReturn(new Receiving([
            'text' => $text,
            'html' => $html,
        ]));

    $emails = Mockery::mock();
    $emails->receiving = $receiving;

    $client = Mockery::mock(ResendClient::class);
    $client->emails = $emails;

    app()->instance(ResendClient::class, $client);
}

it('handles an incoming Resend email and sends a reply', function () {
    mockResendReceiving('Is James available for a new role?');

    $response = $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_test123',
            'email_id' => 'em_test123',
            'from' => 'recruiter@example.com',
            'to' => ['ask@jamesgifford.ai'],
            'subject' => 'Senior Engineer Position',
        ],
    ]);

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

    $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_chan123',
            'email_id' => 'em_chan123',
            'from' => 'hr@company.com',
            'to' => ['ask@jamesgifford.ai'],
            'subject' => 'Role Inquiry',
        ],
    ]);

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

    $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_subj1',
            'email_id' => 'em_subj1',
            'from' => 'hr@company.com',
            'to' => ['ask@jamesgifford.ai'],
            'subject' => 'Engineering Role',
        ],
    ]);

    Mail::assertSent(ProfessionalAssistantReply::class, function ($mail) {
        return $mail->replySubject === 'Re: Engineering Role';
    });
});

it('does not double prepend Re:', function () {
    mockResendReceiving('Thanks for the info.');

    $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_subj2',
            'email_id' => 'em_subj2',
            'from' => 'hr@company.com',
            'to' => ['ask@jamesgifford.ai'],
            'subject' => 'Re: Engineering Role',
        ],
    ]);

    Mail::assertSent(ProfessionalAssistantReply::class, function ($mail) {
        return $mail->replySubject === 'Re: Engineering Role';
    });
});

it('passes the original message ID for threading', function () {
    mockResendReceiving('Hello');

    $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_thread456',
            'email_id' => 'em_thread456',
            'from' => 'test@example.com',
            'to' => ['ask@jamesgifford.ai'],
            'subject' => 'Question',
        ],
    ]);

    Mail::assertSent(ProfessionalAssistantReply::class, function ($mail) {
        return $mail->originalMessageId === 'em_thread456';
    });
});

it('ignores auto-reply emails from mailer-daemon', function () {
    $response = $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_auto1',
            'email_id' => 'em_auto1',
            'from' => 'mailer-daemon@example.com',
            'to' => ['ask@jamesgifford.ai'],
            'subject' => 'Undeliverable',
        ],
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'auto-reply']);

    Mail::assertNothingSent();
});

it('ignores out of office replies', function () {
    $response = $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_ooo1',
            'email_id' => 'em_ooo1',
            'from' => 'person@example.com',
            'to' => ['ask@jamesgifford.ai'],
            'subject' => 'Out of Office Auto-Reply',
        ],
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'auto-reply']);

    Mail::assertNothingSent();
});

it('ignores noreply senders', function () {
    $response = $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_noreply1',
            'email_id' => 'em_noreply1',
            'from' => 'noreply@company.com',
            'to' => ['ask@jamesgifford.ai'],
            'subject' => 'Notification',
        ],
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'auto-reply']);

    Mail::assertNothingSent();
});

it('ignores emails from the inbound address to prevent loops', function () {
    config(['services.resend.inbound_address' => 'ask@jamesgifford.ai']);

    $response = $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_loop1',
            'email_id' => 'em_loop1',
            'from' => 'ask@jamesgifford.ai',
            'to' => ['ask@jamesgifford.ai'],
            'subject' => 'Loop test',
        ],
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'auto-reply']);

    Mail::assertNothingSent();
});

it('uses default subject when none is provided', function () {
    mockResendReceiving('Hello');

    $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_nosub',
            'email_id' => 'em_nosub',
            'from' => 'test@example.com',
            'to' => ['ask@jamesgifford.ai'],
            'subject' => '',
        ],
    ]);

    Mail::assertSent(ProfessionalAssistantReply::class, function ($mail) {
        return $mail->replySubject === 'Re: Professional Inquiry';
    });
});

it('ignores emails with empty body', function () {
    mockResendReceiving('');

    $response = $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_empty',
            'email_id' => 'em_empty',
            'from' => 'empty@example.com',
            'to' => ['ask@jamesgifford.ai'],
            'subject' => 'Empty',
        ],
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'empty body']);
});

it('falls back to HTML body when text is empty', function () {
    mockResendReceiving('', '<p>Tell me about James.</p>');

    $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_html1',
            'email_id' => 'em_html1',
            'from' => 'html@example.com',
            'to' => ['ask@jamesgifford.ai'],
            'subject' => 'HTML Email',
        ],
    ]);

    $this->assertDatabaseHas('conversations', [
        'session_key' => 'html@example.com',
        'channel' => 'email',
    ]);

    Mail::assertSent(ProfessionalAssistantReply::class);
});

it('ignores unsupported event types', function () {
    $response = $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.sent',
        'data' => [
            'id' => 'em_sent1',
        ],
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'unsupported event type']);

    Mail::assertNothingSent();
});

it('strips quoted reply content from email body', function () {
    mockResendReceiving("What about his salary?\n\nOn March 22, 2026 someone wrote:\n> Previous message content here");

    $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_quoted',
            'email_id' => 'em_quoted',
            'from' => 'quoter@example.com',
            'to' => ['ask@jamesgifford.ai'],
            'subject' => 'Re: Follow up',
        ],
    ]);

    $conversation = Conversation::where('session_key', 'quoter@example.com')->first();
    $userMessage = $conversation->messages()->where('role', 'user')->first();

    expect($userMessage->content)->toBe('What about his salary?');
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
