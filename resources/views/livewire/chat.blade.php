<div class="flex flex-col h-screen bg-[#0a0a0a] text-[#EDEDEC]" x-data="{ init() { this.scrollToBottom() } }" x-init="init()">
    {{-- Header --}}
    <header class="flex items-center justify-between px-6 py-4 border-b border-[#3E3E3A]">
        <div class="flex items-center gap-3">
            <flux:heading size="lg" class="!text-[#EDEDEC]">{{ config('app.name') }}</flux:heading>
        </div>

        @if (Route::has('login'))
            <nav class="flex items-center gap-3">
                @auth
                    <flux:button variant="subtle" href="{{ route('dashboard') }}" class="!text-[#A1A09A]">Dashboard</flux:button>
                @else
                    <flux:button variant="subtle" href="{{ route('login') }}" class="!text-[#A1A09A]">Log in</flux:button>
                    @if (Route::has('register'))
                        <flux:button variant="outline" href="{{ route('register') }}">Register</flux:button>
                    @endif
                @endauth
            </nav>
        @endif
    </header>

    {{-- Messages --}}
    <div
        class="flex-1 overflow-y-auto px-4 py-6 space-y-4"
        x-ref="messagesContainer"
    >
        @if (empty($messages))
            <div class="flex items-center justify-center h-full">
                <flux:text class="!text-[#706f6c] text-lg">Send a message to start a conversation.</flux:text>
            </div>
        @endif

        @foreach ($messages as $index => $message)
            <div wire:key="msg-{{ $index }}" class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-2xl px-4 py-3 rounded-2xl {{ $message['role'] === 'user' ? 'bg-blue-600 text-white' : 'bg-[#1a1a18] text-[#EDEDEC] border border-[#3E3E3A]' }}">
                    <div class="whitespace-pre-wrap break-words">{{ $message['content'] }}</div>
                </div>
            </div>
        @endforeach

        {{-- Loading indicator --}}
        <div wire:loading wire:target="sendMessage" class="flex justify-start">
            <div class="max-w-2xl px-4 py-3 rounded-2xl bg-[#1a1a18] text-[#A1A09A] border border-[#3E3E3A]">
                <div class="flex items-center gap-2">
                    <flux:icon.arrow-path class="w-4 h-4 animate-spin" />
                    <span>Thinking...</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Input area --}}
    <div class="border-t border-[#3E3E3A] px-4 py-4">
        <form
            wire:submit="sendMessage"
            class="flex items-end gap-3 max-w-4xl mx-auto"
            x-data
            @keydown.enter.prevent="if (!$event.shiftKey) { $wire.sendMessage() }"
        >
            <div class="flex-1">
                <flux:textarea
                    wire:model="input"
                    placeholder="Type your message..."
                    rows="auto"
                    resize="none"
                    class="!bg-[#161615] !border-[#3E3E3A] !text-[#EDEDEC]"
                    x-bind:disabled="$wire.isProcessing"
                />
                <flux:error name="input" />
            </div>

            <flux:button
                type="submit"
                variant="primary"
                icon="paper-airplane"
                class="mb-0.5 data-loading:opacity-50"
                x-bind:disabled="$wire.isProcessing"
            />
        </form>
    </div>

    @script
    <script>
        $wire.on('message-sent', () => {
            $nextTick(() => {
                const container = $refs.messagesContainer ?? document.querySelector('[x-ref="messagesContainer"]');
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        });

        // Auto-scroll on Livewire DOM updates
        Livewire.hook('morph.updated', () => {
            $nextTick(() => {
                const container = $refs.messagesContainer ?? document.querySelector('[x-ref="messagesContainer"]');
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        });
    </script>
    @endscript
</div>
