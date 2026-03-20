<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\AiProviderService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SmsController extends Controller
{
    public function __construct(
        private AiProviderService $aiService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $from = $request->input('From', '');
        $body = $request->input('Body', '');
        $messageSid = $request->input('MessageSid', '');

        Log::info('SMS received', ['from' => $from, 'body' => $body]);

        $conversation = Conversation::firstOrCreate(
            ['session_key' => $from],
            ['channel' => 'sms', 'metadata' => []],
        );

        $metadata = $conversation->metadata ?? [];
        $metadata['twilio_message_sid'] = $messageSid;
        $metadata['phone_number'] = $from;
        $conversation->metadata = $metadata;
        $conversation->channel = 'sms';
        $conversation->save();

        try {
            $result = $this->aiService->chat($conversation, $body);
            $responseText = $result['response'];
        } catch (\Throwable $e) {
            Log::error('AI provider failure for SMS', ['error' => $e->getMessage()]);
            $responseText = "I'm experiencing technical difficulties right now. Please try again shortly or reach James directly at james@jamesgifford.com";

            $conversation->appendMessage('assistant', $responseText, [
                'error' => true,
                'exception' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);
        }

        $twiml = $this->buildTwimlResponse($responseText);

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }

    private function buildTwimlResponse(string $message): string
    {
        $segments = $this->splitMessage($message, 1600);

        $messagesXml = '';
        foreach ($segments as $segment) {
            $escaped = htmlspecialchars($segment, ENT_XML1, 'UTF-8');
            $messagesXml .= "<Message>{$escaped}</Message>";
        }

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?><Response>{$messagesXml}</Response>";
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
