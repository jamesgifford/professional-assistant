<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChatRequest;
use App\Models\Conversation;
use App\Services\AiProviderService;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function __construct(
        private AiProviderService $aiService,
    ) {}

    public function store(ChatRequest $request): JsonResponse
    {
        $conversation = Conversation::firstOrCreate(
            ['session_key' => $request->validated('session_key')],
            ['channel' => 'api', 'messages' => []],
        );

        $result = $this->aiService->chat($conversation, $request->validated('message'));

        return response()->json([
            'response' => $result['response'],
            'session_key' => $conversation->session_key,
            'provider' => $result['provider'],
        ]);
    }

    public function test(): JsonResponse
    {
        $conversation = Conversation::firstOrCreate(
            ['session_key' => 'test-route'],
            ['channel' => 'api', 'messages' => []],
        );

        $result = $this->aiService->chat($conversation, "What is James's primary tech stack?");

        return response()->json([
            'response' => $result['response'],
            'session_key' => 'test-route',
            'provider' => $result['provider'],
        ]);
    }
}
