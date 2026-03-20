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

class EmailController extends Controller
{
    public function __construct(
        private AiProviderService $aiService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $sender = $request->input('sender', '');
        $subject = $request->input('subject', '');
        $body = $request->input('stripped-text', $request->input('body-plain', ''));

        Log::info('Email received', ['sender' => $sender, 'subject' => $subject]);

        if ($this->isAutoReply($request)) {
            Log::info('Ignoring auto-reply email', ['sender' => $sender]);

            return response()->json(['status' => 'ignored', 'reason' => 'auto-reply']);
        }

        if (empty($body)) {
            $htmlBody = $request->input('body-html', '');
            $body = strip_tags($htmlBody);
        }

        if (empty(trim($body))) {
            return response()->json(['status' => 'ignored', 'reason' => 'empty body']);
        }

        if (empty($subject)) {
            $subject = 'Professional Inquiry';
        }

        $conversation = Conversation::firstOrCreate(
            ['session_key' => strtolower($sender)],
            ['channel' => 'email', 'messages' => [], 'metadata' => []],
        );

        $metadata = $conversation->metadata ?? [];
        $metadata['email'] = $sender;
        $metadata['last_subject'] = $subject;
        $conversation->metadata = $metadata;
        $conversation->channel = 'email';
        $conversation->save();

        try {
            $result = $this->aiService->chat($conversation, $body);
            $responseText = $result['response'];
        } catch (\Throwable $e) {
            Log::error('AI provider failure for email', ['error' => $e->getMessage()]);
            $responseText = "I'm experiencing technical difficulties right now. Please try again shortly or reach James directly at james@jamesgifford.com";
        }

        $replySubject = str_starts_with(strtolower($subject), 're:')
            ? $subject
            : "Re: {$subject}";

        Mail::to($sender)->send(new ProfessionalAssistantReply($responseText, $replySubject));

        return response()->json(['status' => 'sent']);
    }

    private function isAutoReply(Request $request): bool
    {
        $sender = strtolower($request->input('sender', ''));
        if (str_contains($sender, 'mailer-daemon') || str_contains($sender, 'postmaster')) {
            return true;
        }

        $subject = strtolower($request->input('subject', ''));
        if (str_contains($subject, 'auto-reply') || str_contains($subject, 'out of office') || str_contains($subject, 'automatic reply')) {
            return true;
        }

        $autoSubmitted = strtolower($request->header('X-Auto-Response-Suppress', '') ?: $request->input('X-Auto-Response-Suppress', ''));
        if (! empty($autoSubmitted)) {
            return true;
        }

        $precedence = strtolower($request->header('Precedence', '') ?: $request->input('Precedence', ''));
        if (in_array($precedence, ['auto_reply', 'bulk', 'junk'])) {
            return true;
        }

        return false;
    }
}
