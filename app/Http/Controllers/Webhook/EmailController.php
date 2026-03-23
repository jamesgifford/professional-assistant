<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Mail\ProfessionalAssistantReply;
use App\Models\Conversation;
use App\Services\AiProviderService;
use EmailReplyParser\Parser\EmailParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Resend\Client as ResendClient;

class EmailController extends Controller
{
    private const HOURLY_LIMIT = 10;

    private const DAILY_LIMIT = 30;

    private const BULK_SENDER_PREFIXES = [
        'noreply@',
        'no-reply@',
        'marketing@',
        'newsletter@',
        'notifications@',
    ];

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
            $attachments = $data['attachments'] ?? [];

            Log::info('Resend email received', ['sender' => $sender, 'subject' => $subject]);

            if ($this->isBlacklisted($sender)) {
                Log::info('Rejecting blacklisted sender', ['sender' => $sender]);

                return response()->json(['status' => 'ignored', 'reason' => 'blacklisted']);
            }

            $isWhitelisted = $this->isWhitelisted($sender);

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

            if (! $isWhitelisted && $this->isBulkEmail($sender, $emailContent)) {
                Log::info('Ignoring bulk email', ['sender' => $sender]);

                return response()->json(['status' => 'ignored', 'reason' => 'bulk email']);
            }

            $body = $this->extractBody($emailContent);

            if (empty(trim($body))) {
                return response()->json(['status' => 'ignored', 'reason' => 'empty body']);
            }

            if (empty($subject)) {
                $subject = 'Professional Inquiry';
            }

            $hasAttachments = ! empty($attachments);

            if ($hasAttachments) {
                $body = "[Note: The sender included file attachments with this email, but you cannot read them. Acknowledge this and suggest they paste relevant content directly.]\n\n".$body;
            }

            $conversation = Conversation::firstOrCreate(
                ['session_key' => strtolower($sender)],
                ['channel' => 'email', 'metadata' => []],
            );

            $conversationMetadata = $conversation->metadata ?? [];
            $conversationMetadata['email'] = $sender;
            $conversationMetadata['subject'] = $subject;
            $conversationMetadata['message_id'] = $originalMessageId;
            $conversationMetadata['thread_id'] = $data['thread_id'] ?? $originalMessageId;
            $conversation->metadata = $conversationMetadata;
            $conversation->channel = 'email';
            $conversation->save();

            $messageMetadata = [
                'email' => $sender,
                'subject' => $subject,
            ];

            if ($hasAttachments) {
                $messageMetadata['has_attachments'] = true;
                $messageMetadata['attachment_count'] = count($attachments);
            }

            if (! $isWhitelisted) {
                $rateLimitResponse = $this->checkRateLimit($sender, $conversation, $subject, $originalMessageId, $messageMetadata);

                if ($rateLimitResponse) {
                    return $rateLimitResponse;
                }
            }

            try {
                $result = $this->aiService->chat($conversation, $body, $messageMetadata);
                $responseText = $result['response'];
            } catch (\Throwable $e) {
                Log::error('AI provider failure for email', ['error' => $e->getMessage()]);
                $responseText = "I'm experiencing technical difficulties right now. Please try again shortly, or reach James directly at james@jamesgifford.com";

                $conversation->appendMessage('assistant', $responseText, array_merge($messageMetadata, [
                    'error' => true,
                    'exception' => get_class($e),
                    'exception_message' => $e->getMessage(),
                ]));
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

    private function isWhitelisted(string $sender): bool
    {
        $whitelist = config('services.resend.whitelist', []);

        return in_array(strtolower($sender), array_map('strtolower', $whitelist));
    }

    private function isBlacklisted(string $sender): bool
    {
        $blacklist = config('services.resend.blacklist', []);

        return in_array(strtolower($sender), array_map('strtolower', $blacklist));
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
     * Check if this is a bulk/marketing email based on headers and sender prefix.
     *
     * @param  array{text: string|null, html: string|null, headers: array}  $emailContent
     */
    private function isBulkEmail(string $sender, array $emailContent): bool
    {
        $senderLower = strtolower($sender);

        foreach (self::BULK_SENDER_PREFIXES as $prefix) {
            if (str_starts_with($senderLower, $prefix)) {
                return true;
            }
        }

        foreach ($emailContent['headers'] as $header) {
            $name = strtolower($header['name'] ?? '');
            $value = strtolower($header['value'] ?? '');

            if ($name === 'list-unsubscribe') {
                return true;
            }

            if ($name === 'precedence' && in_array($value, ['bulk', 'list'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check per-sender rate limits. Returns a response if rate-limited, null otherwise.
     *
     * @param  array<string, mixed>  $messageMetadata
     */
    private function checkRateLimit(
        string $sender,
        Conversation $conversation,
        string $subject,
        ?string $originalMessageId,
        array $messageMetadata,
    ): ?JsonResponse {
        $emailKey = strtolower($sender);
        $hourKey = "email_rate:{$emailKey}:hour";
        $dayKey = "email_rate:{$emailKey}:day";

        $hourCount = (int) Cache::get($hourKey, 0);
        $dayCount = (int) Cache::get($dayKey, 0);

        if ($hourCount >= self::HOURLY_LIMIT || $dayCount >= self::DAILY_LIMIT) {
            Log::info('Email rate limited', ['sender' => $sender, 'hour' => $hourCount, 'day' => $dayCount]);

            $rateLimitMessage = "I've reached my conversation limit. Please try again later, or reach James directly at james@jamesgifford.com";

            $conversation->appendMessage('user', '[rate limited]', $messageMetadata);
            $conversation->appendMessage('assistant', $rateLimitMessage, array_merge($messageMetadata, [
                'rate_limited' => true,
            ]));

            $replySubject = str_starts_with(strtolower($subject), 're:')
                ? $subject
                : "Re: {$subject}";

            Mail::to($sender)->send(new ProfessionalAssistantReply(
                $rateLimitMessage,
                $replySubject,
                $originalMessageId,
            ));

            return response()->json(['status' => 'rate_limited']);
        }

        Cache::put($hourKey, $hourCount + 1, now()->addMinutes(60));
        Cache::put($dayKey, $dayCount + 1, now()->addHours(24));

        return null;
    }

    /**
     * Fetch the full email content from Resend's Received Emails API.
     *
     * @return array{text: string|null, html: string|null, headers: array}
     */
    private function fetchEmailContent(string $emailId): array
    {
        $email = $this->resend->emails->receiving->get($emailId);

        return [
            'text' => $email['text'] ?? null,
            'html' => $email['html'] ?? null,
            'headers' => $email['headers'] ?? [],
        ];
    }

    /**
     * @param  array{text: string|null, html: string|null, headers: array}  $data
     */
    private function extractBody(array $data): string
    {
        $body = $data['text'] ?? '';

        if (empty($body)) {
            $html = $data['html'] ?? '';
            $body = strip_tags($html);
        }

        if (empty(trim($body))) {
            return '';
        }

        return $this->stripQuotedContent($body);
    }

    private function stripQuotedContent(string $body): string
    {
        $parsed = (new EmailParser)->parse($body);
        $visibleText = $parsed->getVisibleText();

        return $this->stripRemainingNoise($visibleText);
    }

    private function stripRemainingNoise(string $body): string
    {
        $lines = explode("\n", $body);
        $cleaned = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, '>')) {
                continue;
            }

            if ($trimmed === '--') {
                break;
            }

            if (str_starts_with($trimmed, '___')) {
                break;
            }

            if (preg_match('/^On .+ wrote:$/i', $trimmed)) {
                break;
            }

            if (preg_match('/^(Sent from my (iPhone|iPad)|Sent from Outlook|Get Outlook for)/i', $trimmed)) {
                break;
            }

            $cleaned[] = $line;
        }

        return trim(implode("\n", $cleaned));
    }
}
