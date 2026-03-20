<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Services\AiProviderService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Chat extends Component
{
    /** @var array<int, array{role: string, content: string}> */
    public array $messages = [];

    #[Validate('required|string|max:5000')]
    public string $input = '';

    public bool $isProcessing = false;

    public function mount(): void
    {
        $conversation = $this->getConversation();

        if ($conversation) {
            $this->messages = $conversation->messages()
                ->oldest()
                ->get(['role', 'content'])
                ->map(fn ($msg) => [
                    'role' => $msg->role,
                    'content' => $msg->content,
                ])
                ->all();
        }

        $this->prependGreeting();
    }

    public function sendMessage(): void
    {
        $this->validate();

        $message = trim($this->input);
        $this->input = '';

        $this->messages[] = ['role' => 'user', 'content' => $message];
        $this->isProcessing = true;

        try {
            $conversation = $this->getOrCreateConversation();
            $aiService = app(AiProviderService::class);

            $result = $aiService->chat($conversation, $message);

            $this->messages[] = ['role' => 'assistant', 'content' => $result['response']];
        } catch (\Throwable $e) {
            Log::error('Chat error', ['error' => $e->getMessage()]);

            $errorMessage = 'Sorry, something went wrong. Please try again.';
            $this->messages[] = ['role' => 'assistant', 'content' => $errorMessage];

            $conversation->appendMessage('assistant', $errorMessage, [
                'error' => true,
                'exception' => get_class($e),
                'exception_message' => $e->getMessage(),
            ]);
        } finally {
            $this->isProcessing = false;

            $this->dispatch('message-sent');
        }
    }

    private function prependGreeting(): void
    {
        $greetings = config('chat.greetings', []);

        if (empty($greetings)) {
            return;
        }

        $greeting = session()->remember('chat_greeting', fn () => $greetings[array_rand($greetings)]);

        array_unshift($this->messages, ['role' => 'assistant', 'content' => $greeting]);
    }

    private function getSessionKey(): string
    {
        return 'web-'.session()->getId();
    }

    private function getConversation(): ?Conversation
    {
        return Conversation::where('session_key', $this->getSessionKey())
            ->where('channel', 'web')
            ->latest()
            ->first();
    }

    private function getOrCreateConversation(): Conversation
    {
        return Conversation::firstOrCreate(
            [
                'session_key' => $this->getSessionKey(),
                'channel' => 'web',
            ],
            [
                'metadata' => [],
            ]
        );
    }
}
