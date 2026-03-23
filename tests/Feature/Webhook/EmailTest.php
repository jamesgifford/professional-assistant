<?php

use App\Ai\Agents\ProfessionalAssistant;
use App\Mail\ProfessionalAssistantReply;
use App\Models\Conversation;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    ProfessionalAssistant::fake(['James is available for remote roles.']);
    Mail::fake();
});

it('handles an incoming Resend email and sends a reply', function () {
    $response = $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_test123',
            'from' => 'recruiter@example.com',
            'to' => ['prompt@jamesgifford.ai'],
            'subject' => 'Senior Engineer Position',
            'text' => 'Is James available for a new role?',
            'html' => null,
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

it('stores channel on individual messages', function () {
    $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_chan123',
            'from' => 'hr@company.com',
            'to' => ['prompt@jamesgifford.ai'],
            'subject' => 'Role Inquiry',
            'text' => 'Tell me about James.',
            'html' => null,
        ],
    ]);

    $conversation = Conversation::where('session_key', 'hr@company.com')->first();

    expect($conversation)->not->toBeNull();
    expect($conversation->channel)->toBe('email');

    $messages = $conversation->messages()->oldest()->get();

    expect($messages)->toHaveCount(2);
    expect($messages[0]->channel)->toBe('email');
    expect($messages[1]->channel)->toBe('email');
});

it('prepends Re: to the subject if not already present', function () {
    $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_subj1',
            'from' => 'hr@company.com',
            'to' => ['prompt@jamesgifford.ai'],
            'subject' => 'Engineering Role',
            'text' => 'Tell me about James.',
            'html' => null,
        ],
    ]);

    Mail::assertSent(ProfessionalAssistantReply::class, function ($mail) {
        return $mail->replySubject === 'Re: Engineering Role';
    });
});

it('does not double prepend Re:', function () {
    $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_subj2',
            'from' => 'hr@company.com',
            'to' => ['prompt@jamesgifford.ai'],
            'subject' => 'Re: Engineering Role',
            'text' => 'Thanks for the info.',
            'html' => null,
        ],
    ]);

    Mail::assertSent(ProfessionalAssistantReply::class, function ($mail) {
        return $mail->replySubject === 'Re: Engineering Role';
    });
});

it('passes the original message ID for threading', function () {
    $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_thread456',
            'from' => 'test@example.com',
            'to' => ['prompt@jamesgifford.ai'],
            'subject' => 'Question',
            'text' => 'Hello',
            'html' => null,
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
            'from' => 'mailer-daemon@example.com',
            'to' => ['prompt@jamesgifford.ai'],
            'subject' => 'Undeliverable',
            'text' => 'Message not delivered.',
            'html' => null,
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
            'from' => 'person@example.com',
            'to' => ['prompt@jamesgifford.ai'],
            'subject' => 'Out of Office Auto-Reply',
            'text' => 'I am out of the office.',
            'html' => null,
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
            'from' => 'noreply@company.com',
            'to' => ['prompt@jamesgifford.ai'],
            'subject' => 'Notification',
            'text' => 'Some notification.',
            'html' => null,
        ],
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'auto-reply']);

    Mail::assertNothingSent();
});

it('ignores emails from the inbound address to prevent loops', function () {
    config(['services.resend.inbound_address' => 'prompt@jamesgifford.ai']);

    $response = $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_loop1',
            'from' => 'prompt@jamesgifford.ai',
            'to' => ['prompt@jamesgifford.ai'],
            'subject' => 'Loop test',
            'text' => 'This should be ignored.',
            'html' => null,
        ],
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'auto-reply']);

    Mail::assertNothingSent();
});

it('uses default subject when none is provided', function () {
    $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_nosub',
            'from' => 'test@example.com',
            'to' => ['prompt@jamesgifford.ai'],
            'subject' => '',
            'text' => 'Hello',
            'html' => null,
        ],
    ]);

    Mail::assertSent(ProfessionalAssistantReply::class, function ($mail) {
        return $mail->replySubject === 'Re: Professional Inquiry';
    });
});

it('ignores emails with empty body', function () {
    $response = $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_empty',
            'from' => 'empty@example.com',
            'to' => ['prompt@jamesgifford.ai'],
            'subject' => 'Empty',
            'text' => '',
            'html' => null,
        ],
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'empty body']);
});

it('falls back to HTML body when text is empty', function () {
    $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_html1',
            'from' => 'html@example.com',
            'to' => ['prompt@jamesgifford.ai'],
            'subject' => 'HTML Email',
            'text' => '',
            'html' => '<p>Tell me about James.</p>',
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
    $this->postJson('/webhook/resend/inbound', [
        'type' => 'email.received',
        'data' => [
            'id' => 'em_quoted',
            'from' => 'quoter@example.com',
            'to' => ['prompt@jamesgifford.ai'],
            'subject' => 'Re: Follow up',
            'text' => "What about his salary?\n\nOn March 22, 2026 someone wrote:\n> Previous message content here",
            'html' => null,
        ],
    ]);

    $conversation = Conversation::where('session_key', 'quoter@example.com')->first();
    $userMessage = $conversation->messages()->where('role', 'user')->first();

    expect($userMessage->content)->toBe('What about his salary?');
});
