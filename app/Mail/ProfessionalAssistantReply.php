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
        public ?string $originalMessageId = null,
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = config('services.resend.inbound_address', config('mail.from.address'));

        return new Envelope(
            from: $fromAddress,
            replyTo: [$fromAddress],
            subject: $this->replySubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.professional-assistant-reply-html',
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

    public function build(): static
    {
        if ($this->originalMessageId) {
            $this->withSymfonyMessage(function ($message) {
                $message->getHeaders()->addTextHeader('In-Reply-To', $this->originalMessageId);
                $message->getHeaders()->addTextHeader('References', $this->originalMessageId);
            });
        }

        return $this;
    }
}
