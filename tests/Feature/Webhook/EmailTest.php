<?php

use App\Ai\Agents\ProfessionalAssistant;
use App\Mail\ProfessionalAssistantReply;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    ProfessionalAssistant::fake(['James is available for remote roles.']);
    Mail::fake();
});

it('handles an incoming email and sends a reply', function () {
    $response = $this->postJson('/webhook/email', [
        'sender' => 'recruiter@example.com',
        'subject' => 'Senior Engineer Position',
        'stripped-text' => 'Is James available for a new role?',
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

it('prepends Re: to the subject if not already present', function () {
    $this->postJson('/webhook/email', [
        'sender' => 'hr@company.com',
        'subject' => 'Engineering Role',
        'stripped-text' => 'Tell me about James.',
    ]);

    Mail::assertSent(ProfessionalAssistantReply::class, function ($mail) {
        return $mail->replySubject === 'Re: Engineering Role';
    });
});

it('does not double prepend Re:', function () {
    $this->postJson('/webhook/email', [
        'sender' => 'hr@company.com',
        'subject' => 'Re: Engineering Role',
        'stripped-text' => 'Thanks for the info.',
    ]);

    Mail::assertSent(ProfessionalAssistantReply::class, function ($mail) {
        return $mail->replySubject === 'Re: Engineering Role';
    });
});

it('ignores auto-reply emails', function () {
    $response = $this->postJson('/webhook/email', [
        'sender' => 'mailer-daemon@example.com',
        'subject' => 'Undeliverable',
        'stripped-text' => 'Message not delivered.',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'auto-reply']);

    Mail::assertNothingSent();
});

it('ignores out of office replies', function () {
    $response = $this->postJson('/webhook/email', [
        'sender' => 'person@example.com',
        'subject' => 'Out of Office Auto-Reply',
        'stripped-text' => 'I am out of the office.',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'auto-reply']);

    Mail::assertNothingSent();
});

it('uses default subject when none is provided', function () {
    $this->postJson('/webhook/email', [
        'sender' => 'test@example.com',
        'subject' => '',
        'stripped-text' => 'Hello',
    ]);

    Mail::assertSent(ProfessionalAssistantReply::class, function ($mail) {
        return $mail->replySubject === 'Re: Professional Inquiry';
    });
});

it('ignores emails with empty body', function () {
    $response = $this->postJson('/webhook/email', [
        'sender' => 'empty@example.com',
        'subject' => 'Empty',
        'stripped-text' => '',
    ]);

    $response->assertSuccessful()
        ->assertJson(['status' => 'ignored', 'reason' => 'empty body']);
});
