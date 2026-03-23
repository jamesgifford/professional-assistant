<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Mail\ProfessionalAssistantReply;
use App\Models\Conversation;
use App\Services\AiProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Resend\Client as ResendClient;

class EmailController extends Controller
{
    public function __construct(
        private AiProviderService $aiService,
        private ResendClient $resend,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            if ($request->input('type') !== 'email.received') {
                return response()->json(['status' => 'ignored', 'reason' => 'unsupported event type']);
            }

            $data = $request->input('data', []);
            $sender = $data['from'] ?? '';
            $subject = $data['subject'] ?? '';
            $originalMessageId = $data['id'] ?? null;

            Log::info('Resend email received', ['sender' => $sender, 'subject' => $subject]);

            if ($this->shouldIgnore($sender, $subject, $data)) {
                Log::info('Ignoring email', ['sender' => $sender, 'reason' => 'auto-reply or loop']);

                return response()->json(['status' => 'ignored', 'reason' => 'auto-reply']);
            }

            $emailId = $data['email_id'] ?? null;

            if (empty($emailId)) {
                Log::warning('Resend webhook missing email_id', ['data' => $data]);

                return response()->json(['status' => 'ignored', 'reason' => 'missing email_id']);
            }

            $emailContent = $this->fetchEmailContent($emailId);
            $body = $this->extractBody($emailContent);

            if (empty(trim($body))) {
                return response()->json(['status' => 'ignored', 'reason' => 'empty body']);
            }

            if (empty($subject)) {
                $subject = 'Professional Inquiry';
            }

            $conversation = Conversation::firstOrCreate(
                ['session_key' => strtolower($sender)],
                ['channel' => 'email', 'metadata' => []],
            );

            $metadata = $conversation->metadata ?? [];
            $metadata['email'] = $sender;
            $metadata['last_subject'] = $subject;
            $metadata['last_message_id'] = $originalMessageId;
            $conversation->metadata = $metadata;
            $conversation->channel = 'email';
            $conversation->save();

            try {
                $result = $this->aiService->chat($conversation, $body);
                $responseText = $result['response'];
            } catch (\Throwable $e) {
                Log::error('AI provider failure for email', ['error' => $e->getMessage()]);
                $responseText = "I'm experiencing technical difficulties right now. Please try again shortly, or reach James directly at james@jamesgifford.com";

                $conversation->appendMessage('assistant', $responseText, [
                    'error' => true,
                    'exception' => get_class($e),
                    'exception_message' => $e->getMessage(),
                ]);
            }

            $replySubject = str_starts_with(strtolower($subject), 're:')
                ? $subject
                : "Re: {$subject}";

            Mail::to($sender)->send(new ProfessionalAssistantReply(
                $responseText,
                $replySubject,
                $originalMessageId,
            ));

            return response()->json(['status' => 'sent']);
        } catch (\Throwable $e) {
            Log::error('Resend webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    private function shouldIgnore(string $sender, string $subject, array $data): bool
    {
        $senderLower = strtolower($sender);
        $inboundAddress = strtolower(config('services.resend.inbound_address', ''));

        if ($senderLower === $inboundAddress) {
            return true;
        }

        if (str_contains($senderLower, 'mailer-daemon') || str_contains($senderLower, 'postmaster') || str_contains($senderLower, 'noreply')) {
            return true;
        }

        $subjectLower = strtolower($subject);
        if (str_contains($subjectLower, 'auto-reply') || str_contains($subjectLower, 'out of office') || str_contains($subjectLower, 'automatic reply')) {
            return true;
        }

        $headers = $data['headers'] ?? [];
        foreach ($headers as $header) {
            $name = strtolower($header['name'] ?? '');
            $value = strtolower($header['value'] ?? '');

            if ($name === 'x-auto-responded-to' || ($name === 'auto-submitted' && str_contains($value, 'auto-replied'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch the full email content from Resend's Received Emails API.
     *
     * @return array{text: string|null, html: string|null}
     */
    private function fetchEmailContent(string $emailId): array
    {
        $email = $this->resend->emails->receiving->get($emailId);

        return [
            'text' => $email['text'] ?? null,
            'html' => $email['html'] ?? null,
        ];
    }

    private function extractBody(array $data): string
    {
        $body = $data['text'] ?? '';

        if (empty($body)) {
            $html = $data['html'] ?? '';
            $body = strip_tags($html);
        }

        return $this->stripQuotedContent($body);
    }

    private function stripQuotedContent(string $body): string
    {
        $lines = explode("\n", $body);
        $cleaned = [];

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '>')) {
                continue;
            }

            if (trim($line) === '--' || str_starts_with(trim($line), '___')) {
                break;
            }

            if (preg_match('/^On .+ wrote:$/i', trim($line))) {
                break;
            }

            $cleaned[] = $line;
        }

        return trim(implode("\n", $cleaned));
    }
}
