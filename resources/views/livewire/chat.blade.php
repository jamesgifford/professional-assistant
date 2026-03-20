<div
    id="chat"
    class="flex flex-col min-h-[calc(100vh-5rem)]"
    x-data="{
        healthStatus: 'unknown',
        init() {
            this.scrollToBottom();
            this.checkHealth();
            setInterval(() => this.checkHealth(), 60000);
        },
        scrollToBottom() {
            $nextTick(() => {
                const container = this.$refs.messagesContainer;
                if (container) {
                    const inner = container.firstElementChild;
                    if (inner && inner.lastElementChild) {
                        inner.lastElementChild.scrollIntoView({ behavior: 'instant', block: 'end' });
                    }
                }
            });
        },
        async checkHealth() {
            try {
                const res = await fetch('/api/health');
                const data = await res.json();
                const active = data.providers?.[data.active_provider];
                this.healthStatus = (active?.status === 'up') ? 'up' : 'down';
            } catch {
                this.healthStatus = 'down';
            }
        },
        fillPrompt(text) {
            $wire.set('input', text);
            $nextTick(() => $wire.sendMessage());
        }
    }"
    x-init="init()"
>
    {{-- Messages --}}
    <div
        class="flex-1 overflow-y-auto"
        x-ref="messagesContainer"
    >
        <div class="max-w-[720px] mx-auto px-6 py-8 pb-40 space-y-5">
            @foreach ($messages as $index => $message)
                @if ($index === 0 && $message['role'] === 'assistant' && count($messages) === 1)
                    {{-- Greeting with suggested prompts --}}
                    <div class="flex items-center justify-center h-full min-h-[50vh]">
                        <div class="text-center max-w-md">
                            <div class="flex items-center justify-center gap-2 mb-4">
                                <span class="font-mono text-xs text-emerald-600 dark:text-emerald-400">// hiring assistant</span>
                                <span
                                    class="inline-block w-2 h-2 rounded-full"
                                    :class="healthStatus === 'up' ? 'bg-emerald-500' : (healthStatus === 'down' ? 'bg-red-500' : 'bg-zinc-400')"
                                    :title="healthStatus === 'up' ? 'AI service online' : (healthStatus === 'down' ? 'AI service offline' : 'Checking status...')"
                                ></span>
                            </div>

                            <div class="px-4 py-3 rounded-lg text-[15px] leading-[1.6] bg-zinc-50/95 border border-zinc-200 text-zinc-900 dark:bg-zinc-950/95 dark:border-zinc-800 dark:text-zinc-100 text-left mb-8">
                                <div class="prose prose-zinc dark:prose-invert prose-sm max-w-none prose-pre:bg-zinc-100 prose-pre:dark:bg-zinc-800 prose-pre:font-mono prose-pre:text-sm prose-code:font-mono prose-code:text-sm prose-code:before:content-none prose-code:after:content-none prose-a:text-emerald-600 prose-a:dark:text-emerald-400">{!! \Illuminate\Support\Str::markdown($message['content']) !!}</div>
                            </div>

                            <div class="flex flex-wrap justify-center gap-2">
                                <button
                                    @click="fillPrompt('Tell me about James\'s experience')"
                                    class="px-4 py-2 rounded-full border border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800 hover:border-zinc-400 dark:hover:border-zinc-600 transition-colors"
                                >
                                    Tell me about James's experience
                                </button>
                                <button
                                    @click="fillPrompt('What\'s his tech stack?')"
                                    class="px-4 py-2 rounded-full border border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800 hover:border-zinc-400 dark:hover:border-zinc-600 transition-colors"
                                >
                                    What's his tech stack?
                                </button>
                                <button
                                    @click="fillPrompt('What are his salary expectations?')"
                                    class="px-4 py-2 rounded-full border border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800 hover:border-zinc-400 dark:hover:border-zinc-600 transition-colors"
                                >
                                    What are his salary expectations?
                                </button>
                                <button
                                    @click="fillPrompt('How was this assistant built?')"
                                    class="px-4 py-2 rounded-full border border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800 hover:border-zinc-400 dark:hover:border-zinc-600 transition-colors"
                                >
                                    How was this assistant built?
                                </button>
                            </div>
                        </div>
                    </div>
                @else
                <div
                    wire:key="msg-{{ $index }}"
                    class="flex {{ $message['role'] === 'user' ? 'justify-end chat-message-user' : 'justify-start' }} chat-message-enter"
                >
                    <div class="max-w-[85%]">
                        <div class="px-4 py-3 rounded-lg text-[15px] leading-[1.6] {{ $message['role'] === 'user' ? 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' : 'bg-zinc-50/95 border border-zinc-200 text-zinc-900 dark:bg-zinc-950/95 dark:border-zinc-800 dark:text-zinc-100' }}">
                            @if ($message['role'] === 'assistant')
                                <div class="prose prose-zinc dark:prose-invert prose-sm max-w-none prose-pre:bg-zinc-100 prose-pre:dark:bg-zinc-800 prose-pre:font-mono prose-pre:text-sm prose-code:font-mono prose-code:text-sm prose-code:before:content-none prose-code:after:content-none prose-a:text-emerald-600 prose-a:dark:text-emerald-400">{!! \Illuminate\Support\Str::markdown($message['content']) !!}</div>
                            @else
                                <div class="whitespace-pre-wrap break-words">{{ $message['content'] }}</div>
                            @endif
                        </div>
                        <div class="mt-1 px-1 text-xs text-zinc-400 {{ $message['role'] === 'user' ? 'text-right' : 'text-left' }}">
                            {{ $message['role'] === 'user' ? 'You' : 'Assistant' }}
                        </div>
                    </div>
                </div>
                @endif
            @endforeach

            {{-- Typing indicator --}}
            <div wire:loading wire:target="sendMessage" class="flex justify-start chat-message-enter">
                <div class="max-w-[85%]">
                    <div class="px-4 py-3 rounded-lg bg-zinc-50/95 border border-zinc-200 dark:bg-zinc-950/95 dark:border-zinc-800">
                        <div class="flex items-center gap-1.5 h-5">
                            <span class="typing-dot w-1.5 h-1.5 rounded-full bg-zinc-400"></span>
                            <span class="typing-dot w-1.5 h-1.5 rounded-full bg-zinc-400" style="animation-delay: 0.15s"></span>
                            <span class="typing-dot w-1.5 h-1.5 rounded-full bg-zinc-400" style="animation-delay: 0.3s"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Input area --}}
    <div class="fixed bottom-0 inset-x-0 z-40 bg-zinc-50/80 dark:bg-zinc-950/80 backdrop-blur-md">
        <div class="max-w-[720px] mx-auto px-6 py-4">
            <form
                wire:submit="sendMessage"
                x-data="{ menuOpen: false }"
                @keydown.enter="if (!$event.shiftKey && !$wire.isProcessing) { $event.preventDefault(); $wire.sendMessage() }"
            >
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-800">
                    <textarea
                        wire:model="input"
                        x-ref="chatInput"
                        placeholder="Ask about James's experience, skills, or availability..."
                        rows="1"
                        class="chat-input w-full resize-none bg-transparent text-zinc-900 dark:text-zinc-100 text-[15px] px-5 py-4 placeholder-zinc-400 border-0 focus:ring-0 focus:outline-none"
                        x-bind:disabled="$wire.isProcessing"
                        x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 160) + 'px'"
                    ></textarea>

                    <div class="flex items-center justify-between px-2 py-1.5">
                        {{-- Menu button --}}
                        <div class="relative">
                            <button
                                type="button"
                                @click="menuOpen = !menuOpen"
                                x-bind:disabled="$wire.isProcessing"
                                class="w-8 h-8 flex items-center justify-center rounded-md text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                    <path d="M3 10a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0ZM8.5 10a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0ZM15.5 8.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z" />
                                </svg>
                            </button>

                            {{-- Dropdown --}}
                            <div
                                x-show="menuOpen"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                @click.outside="menuOpen = false"
                                @keydown.escape.window="menuOpen = false"
                                class="absolute bottom-full left-0 mb-1 w-44 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg py-1 z-50"
                                x-cloak
                            >
                                <button
                                    type="button"
                                    @click="menuOpen = false; $wire.startNewSession()"
                                    class="flex items-center gap-2 w-full px-3 py-2 text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                        <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 0 1-9.201 2.466l-.312-.311h2.433a.75.75 0 0 0 0-1.5H4.598a.75.75 0 0 0-.75.75v3.634a.75.75 0 0 0 1.5 0v-2.394l.312.311a7 7 0 0 0 11.712-3.138.75.75 0 0 0-1.449-.39Zm1.23-3.723a.75.75 0 0 0-.218-.528 7 7 0 0 0-11.712 3.138.75.75 0 0 0 1.449.39 5.5 5.5 0 0 1 9.201-2.466l.312.311H13.14a.75.75 0 0 0 0 1.5h3.634a.75.75 0 0 0 .75-.75V7.701Z" clip-rule="evenodd" />
                                    </svg>
                                    New session
                                </button>
                                <button
                                    type="button"
                                    @click="menuOpen = false; $flux.dark = !$flux.dark"
                                    class="flex items-center gap-2 w-full px-3 py-2 text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors"
                                >
                                    <svg x-show="!$flux.dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                        <path fill-rule="evenodd" d="M7.455 2.004a.75.75 0 0 1 .26.77 7 7 0 0 0 9.958 7.967.75.75 0 0 1 1.067.853A8.5 8.5 0 1 1 6.647 1.921a.75.75 0 0 1 .808.083Z" clip-rule="evenodd" />
                                    </svg>
                                    <svg x-show="$flux.dark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                        <path d="M10 2a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 2ZM10 15a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 15ZM10 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6ZM15.657 5.404a.75.75 0 1 0-1.06-1.06l-1.061 1.06a.75.75 0 0 0 1.06 1.06l1.061-1.06ZM6.464 14.596a.75.75 0 1 0-1.06-1.06l-1.061 1.06a.75.75 0 0 0 1.06 1.06l1.061-1.06ZM18 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 18 10ZM5 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 5 10ZM14.596 15.657a.75.75 0 0 0 1.06-1.06l-1.06-1.061a.75.75 0 1 0-1.06 1.06l1.06 1.061ZM5.404 6.464a.75.75 0 0 0 1.06-1.06l-1.06-1.061a.75.75 0 1 0-1.06 1.06l1.06 1.061Z" />
                                    </svg>
                                    <span x-text="$flux.dark ? 'Toggle light mode' : 'Toggle dark mode'"></span>
                                </button>
                            </div>
                        </div>

                        {{-- Send button --}}
                        <button
                            type="submit"
                            class="w-8 h-8 rounded-lg bg-zinc-800 dark:bg-white text-white dark:text-zinc-800 flex items-center justify-center transition-opacity hover:opacity-80 disabled:opacity-30 disabled:cursor-not-allowed"
                            x-bind:disabled="$wire.isProcessing"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                <path d="M3.105 2.288a.75.75 0 0 0-.826.95l1.414 4.926A1.5 1.5 0 0 0 5.135 9.25h6.115a.75.75 0 0 1 0 1.5H5.135a1.5 1.5 0 0 0-1.442 1.086l-1.414 4.926a.75.75 0 0 0 .826.95 28.897 28.897 0 0 0 15.293-7.155.75.75 0 0 0 0-1.114A28.897 28.897 0 0 0 3.105 2.288Z" />
                            </svg>
                        </button>
                    </div>
                </div>

                @error('input')
                    <p class="text-red-500 text-xs mt-1.5 px-1">{{ $message }}</p>
                @enderror
            </form>
            <p class="font-mono text-xs text-zinc-400 dark:text-zinc-600 text-center mt-3">Responses are AI-generated and may not be perfectly accurate.<br>For definitive answers, contact James directly.</p>
        </div>
    </div>

    @script
    <script>
        function scrollToLastUserMessage() {
            $nextTick(() => {
                const container = document.querySelector('[x-ref="messagesContainer"]');
                if (!container) return;

                const userMessages = container.querySelectorAll('.chat-message-user');
                const lastUserMessage = userMessages[userMessages.length - 1];

                if (lastUserMessage) {
                    lastUserMessage.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }

        $wire.on('message-sent', scrollToLastUserMessage);
        Livewire.hook('morph.updated', scrollToLastUserMessage);

        $wire.on('session-reset', () => {
            $nextTick(() => {
                const container = document.querySelector('[x-ref="messagesContainer"]');
                if (container) container.scrollTop = 0;
                const textarea = document.querySelector('[x-ref="chatInput"]');
                if (textarea) textarea.style.height = 'auto';
            });
        });
    </script>
    @endscript
</div>
