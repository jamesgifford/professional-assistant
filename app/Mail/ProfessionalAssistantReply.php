<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProfessionalAssistantReply extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $responseText,
        public string $replySubject,
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = config('services.mailgun.inbound_address', config('mail.from.address'));

        return new Envelope(
            from: $fromAddress,
            replyTo: [$fromAddress],
            subject: $this->replySubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.professional-assistant-reply',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
