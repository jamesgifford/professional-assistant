<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\AiProviderService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SmsController extends Controller
{
    private const HOURLY_LIMIT = 10;

    private const DAILY_LIMIT = 20;

    private const OPT_OUT_KEYWORDS = ['stop', 'stopall', 'unsubscribe', 'cancel', 'end'];

    private const OPT_IN_KEYWORDS = ['start', 'unstop'];

    private const SMS_CONTEXT_HINT = '[This conversation is happening over SMS. Keep responses concise — ideally under 320 characters (2 SMS segments). Avoid long lists, code blocks, and detailed technical explanations. Be direct and brief. If the question requires a detailed answer, provide a summary and suggest they visit jamesgifford.ai or email james@jamesgifford.com for more detail.]';

    private const FIRST_MESSAGE_HINT = '[This is the first message from this sender. Briefly introduce yourself as James Gifford\'s AI professional assistant before answering their question.]';

    private const MAX_RESPONSE_LENGTH = 1600;

    public function __construct(
        private AiProviderService $aiService,
    ) {}

    public function __invoke(Request $request): Response
    {
        try {
            $from = $request->input('From', '');
            $body = trim($request->input('Body', ''));
            $messageSid = $request->input('MessageSid', '');
            $numMedia = (int) $request->input('NumMedia', 0);

            $maskedPhone = $this->maskPhoneNumber($from);

            Log::info('SMS received', ['from' => $maskedPhone, 'body' => $body]);

            // Loop prevention: ignore messages from our own number
            if ($this->isOwnNumber($from)) {
                Log::warning('Received SMS from own Twilio number, ignoring', ['from' => $maskedPhone]);

                return $this->emptyTwimlResponse();
            }

            // Opt-in/out keyword handling (checked before blocklist so users can re-subscribe)
            $bodyLower = strtolower($body);

            if (in_array($bodyLower, self::OPT_OUT_KEYWORDS)) {
                return $this->handleOptOut($from);
            }

            if (in_array($bodyLower, self::OPT_IN_KEYWORDS)) {
                return $this->handleOptIn($from);
            }

            if ($bodyLower === 'help') {
                return $this->handleHelp();
            }

            // Blocklist check
            if ($this->isBlocklisted($from)) {
                Log::info('Rejecting blocklisted SMS sender', ['from' => $maskedPhone]);

                return $this->emptyTwimlResponse();
            }

            // Allowlist check
            if (! $this->isAllowed($from)) {
                Log::info('Rejecting non-allowlisted SMS sender', ['from' => $maskedPhone]);

                return $this->emptyTwimlResponse();
            }

            // Empty message handling
            if ($body === '' && $numMedia === 0) {
                return $this->emptyTwimlResponse();
            }

            // MMS media attachment awareness
            $mediaMetadata = [];
            if ($numMedia > 0) {
                $mediaTypes = [];
                for ($i = 0; $i < $numMedia; $i++) {
                    $mediaType = $request->input("MediaContentType{$i}");
                    if ($mediaType) {
                        $mediaTypes[] = $mediaType;
                    }
                }

                $mediaMetadata = [
                    'media_count' => $numMedia,
                    'media_types' => $mediaTypes,
                ];

                $attachmentNote = '[Note: The sender included a media attachment with this message, but you cannot view it. Acknowledge this and suggest they describe the content or email it to james@jamesgifford.com instead.]';

                $body = $body !== '' ? "{$attachmentNote}\n\n{$body}" : $attachmentNote;
            }

            $conversation = Conversation::firstOrCreate(
                ['session_key' => $from],
                ['channel' => 'sms', 'metadata' => []],
            );

            // Update conversation metadata with Twilio data
            $metadata = $conversation->metadata ?? [];
            $metadata['phone_number'] = $from;
            $metadata['twilio_message_sid'] = $messageSid;

            if ($request->input('FromCity')) {
                $metadata['city'] = $request->input('FromCity');
            }
            if ($request->input('FromState')) {
                $metadata['state'] = $request->input('FromState');
            }
            if ($request->input('FromCountry')) {
                $metadata['country'] = $request->input('FromCountry');
            }

            if (! empty($mediaMetadata)) {
                $metadata = array_merge($metadata, $mediaMetadata);
            }

            $conversation->metadata = $metadata;
            $conversation->channel = 'sms';
            $conversation->save();

            $messageMetadata = array_filter([
                'twilio_message_sid' => $messageSid,
                'media_count' => $mediaMetadata['media_count'] ?? null,
                'media_types' => $mediaMetadata['media_types'] ?? null,
            ], fn ($value) => $value !== null);

            // Rate limiting (allowlisted senders bypass)
            if (! $this->isOnAllowlist($from)) {
                $rateLimitResponse = $this->checkRateLimit($from, $conversation, $messageMetadata);

                if ($rateLimitResponse) {
                    return $rateLimitResponse;
                }
            }

            // Build the message with SMS context hints
            $isFirstMessage = $conversation->messages()->count() === 0;
            $contextHints = self::SMS_CONTEXT_HINT;

            if ($isFirstMessage) {
                $contextHints .= "\n\n".self::FIRST_MESSAGE_HINT;
            }

            $enrichedBody = "{$contextHints}\n\n{$body}";

            try {
                $result = $this->aiService->chat($conversation, $enrichedBody, $messageMetadata ?: null);
                $responseText = $result['response'];
            } catch (\Throwable $e) {
                Log::error('AI provider failure for SMS', [
                    'from' => $maskedPhone,
                    'error' => $e->getMessage(),
                ]);

                $responseText = "I'm having technical difficulties. Please try again shortly or reach James at james@jamesgifford.com";

                $conversation->appendMessage('assistant', $responseText, array_merge($messageMetadata, [
                    'error' => true,
                    'exception' => get_class($e),
                    'exception_message' => $e->getMessage(),
                ]));
            }

            // Truncate overly long responses
            $responseText = $this->truncateResponse($responseText);

            return $this->buildTwimlResponse($responseText);
        } catch (\Throwable $e) {
            Log::error('SMS webhook unhandled exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->buildTwimlResponse(
                "I'm having technical difficulties. Please try again shortly or reach James at james@jamesgifford.com"
            );
        }
    }

    private function isOwnNumber(string $from): bool
    {
        $ownNumber = config('services.twilio.phone_number', '');

        return $ownNumber !== '' && $from === $ownNumber;
    }

    private function isBlocklisted(string $from): bool
    {
        $blocklist = config('services.twilio.blocklist', []);
        $dynamicBlocklist = Cache::get('sms_blocklist', []);

        return in_array($from, $blocklist) || in_array($from, $dynamicBlocklist);
    }

    private function isOnAllowlist(string $from): bool
    {
        $allowlist = config('services.twilio.allowlist', []);

        return ! empty($allowlist) && in_array($from, $allowlist);
    }

    private function isAllowed(string $from): bool
    {
        $allowlist = config('services.twilio.allowlist', []);

        // If allowlist is empty, allow all (open mode)
        if (empty($allowlist)) {
            return true;
        }

        return in_array($from, $allowlist);
    }

    private function handleOptOut(string $from): Response
    {
        $blocklist = Cache::get('sms_blocklist', []);

        if (! in_array($from, $blocklist)) {
            $blocklist[] = $from;
            Cache::forever('sms_blocklist', $blocklist);
        }

        return $this->buildTwimlResponse(
            "You've been unsubscribed. You will no longer receive messages from this assistant."
        );
    }

    private function handleOptIn(string $from): Response
    {
        $blocklist = Cache::get('sms_blocklist', []);
        $blocklist = array_values(array_filter($blocklist, fn ($number) => $number !== $from));
        Cache::forever('sms_blocklist', $blocklist);

        return $this->buildTwimlResponse(
            "You've been resubscribed. Send a message to start a conversation with James Gifford's AI professional assistant."
        );
    }

    private function handleHelp(): Response
    {
        return $this->buildTwimlResponse(
            "This is James Gifford's AI professional assistant. Send a message to ask about his professional background, skills, or availability. Reply STOP to unsubscribe. Contact James directly at james@jamesgifford.com"
        );
    }

    /**
     * Check per-sender rate limits. Returns a response if rate-limited, null otherwise.
     *
     * @param  array<string, mixed>  $messageMetadata
     */
    private function checkRateLimit(string $from, Conversation $conversation, array $messageMetadata): ?Response
    {
        $hourKey = "sms_rate:{$from}:hour";
        $dayKey = "sms_rate:{$from}:day";

        $hourCount = (int) Cache::get($hourKey, 0);
        $dayCount = (int) Cache::get($dayKey, 0);

        if ($hourCount >= self::HOURLY_LIMIT || $dayCount >= self::DAILY_LIMIT) {
            $maskedPhone = $this->maskPhoneNumber($from);
            Log::info('SMS rate limited', ['from' => $maskedPhone, 'hour' => $hourCount, 'day' => $dayCount]);

            $rateLimitMessage = "I've reached my conversation limit. Please try again later, or reach James directly at james@jamesgifford.com";

            $conversation->appendMessage('user', '[rate limited]', $messageMetadata);
            $conversation->appendMessage('assistant', $rateLimitMessage, array_merge($messageMetadata, [
                'rate_limited' => true,
            ]));

            return $this->buildTwimlResponse($rateLimitMessage);
        }

        Cache::put($hourKey, $hourCount + 1, now()->addMinutes(60));
        Cache::put($dayKey, $dayCount + 1, now()->addHours(24));

        return null;
    }

    private function truncateResponse(string $response): string
    {
        if (mb_strlen($response) <= self::MAX_RESPONSE_LENGTH) {
            return $response;
        }

        $suffix = '...more at jamesgifford.ai';
        $maxContent = self::MAX_RESPONSE_LENGTH - mb_strlen($suffix);
        $truncated = mb_substr($response, 0, $maxContent);

        // Find the last complete sentence
        $lastPeriod = mb_strrpos($truncated, '.');
        $lastExclamation = mb_strrpos($truncated, '!');
        $lastQuestion = mb_strrpos($truncated, '?');

        $lastSentenceEnd = max($lastPeriod ?: 0, $lastExclamation ?: 0, $lastQuestion ?: 0);

        if ($lastSentenceEnd > $maxContent * 0.5) {
            $truncated = mb_substr($response, 0, $lastSentenceEnd + 1);
        }

        return $truncated.$suffix;
    }

    private function maskPhoneNumber(string $phone): string
    {
        if (mb_strlen($phone) <= 4) {
            return '****';
        }

        return str_repeat('*', mb_strlen($phone) - 4).mb_substr($phone, -4);
    }

    private function emptyTwimlResponse(): Response
    {
        return response(
            '<?xml version="1.0" encoding="UTF-8"?><Response></Response>',
            200,
            ['Content-Type' => 'text/xml'],
        );
    }

    private function buildTwimlResponse(string $message): Response
    {
        $segments = $this->splitMessage($message, self::MAX_RESPONSE_LENGTH);

        $messagesXml = '';
        foreach ($segments as $segment) {
            $escaped = htmlspecialchars($segment, ENT_XML1, 'UTF-8');
            $messagesXml .= "<Message>{$escaped}</Message>";
        }

        $twiml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><Response>{$messagesXml}</Response>";

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * @return string[]
     */
    private function splitMessage(string $message, int $maxLength): array
    {
        if (mb_strlen($message) <= $maxLength) {
            return [$message];
        }

        $segments = [];
        while (mb_strlen($message) > 0) {
            if (mb_strlen($message) <= $maxLength) {
                $segments[] = $message;
                break;
            }

            $chunk = mb_substr($message, 0, $maxLength);
            $lastSpace = mb_strrpos($chunk, ' ');

            if ($lastSpace !== false && $lastSpace > $maxLength * 0.5) {
                $segments[] = mb_substr($message, 0, $lastSpace);
                $message = ltrim(mb_substr($message, $lastSpace));
            } else {
                $segments[] = $chunk;
                $message = mb_substr($message, $maxLength);
            }
        }

        return $segments;
    }
}
