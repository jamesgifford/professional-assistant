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
                if (container) container.scrollTop = container.scrollHeight;
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
        <div class="max-w-[720px] mx-auto px-6 py-8 pb-24 space-y-5">
            @if (empty($messages))
                {{-- Empty state --}}
                <div class="flex items-center justify-center h-full min-h-[50vh]">
                    <div class="text-center max-w-md">
                        <div class="flex items-center justify-center gap-2 mb-1">
                            <span class="font-mono text-xs text-emerald-600 dark:text-emerald-400">// hiring assistant</span>
                            <span
                                class="inline-block w-2 h-2 rounded-full"
                                :class="healthStatus === 'up' ? 'bg-emerald-500' : (healthStatus === 'down' ? 'bg-red-500' : 'bg-zinc-400')"
                                :title="healthStatus === 'up' ? 'AI service online' : (healthStatus === 'down' ? 'AI service offline' : 'Checking status...')"
                            ></span>
                        </div>
                        <h3 class="text-xl font-semibold mb-3 text-zinc-900 dark:text-zinc-100">Hi, I'm James Gifford's AI hiring assistant.</h3>
                        <p class="text-zinc-500 dark:text-zinc-400 text-[15px] leading-relaxed mb-2">I can discuss James's professional background, technical skills, salary expectations, and what he's looking for in his next role.</p>
                        <p class="text-zinc-500 dark:text-zinc-400 text-[15px] leading-relaxed mb-8">Ask me anything.</p>

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
            @endif

            @foreach ($messages as $index => $message)
                <div
                    wire:key="msg-{{ $index }}"
                    class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }} chat-message-enter"
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
    <div class="fixed bottom-0 inset-x-0 z-40 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/80 dark:bg-zinc-950/80 backdrop-blur-md">
        <div class="max-w-[720px] mx-auto px-6 py-4">
            <form
                wire:submit="sendMessage"
                class="relative"
                x-data
                @keydown.enter="if (!$event.shiftKey && !$wire.isProcessing) { $event.preventDefault(); $wire.sendMessage() }"
            >
                <div class="pr-14">
                    <textarea
                        wire:model="input"
                        x-ref="chatInput"
                        placeholder="Ask about James's experience, skills, or availability..."
                        rows="1"
                        class="chat-input w-full resize-none rounded-lg bg-transparent border border-zinc-200 dark:border-zinc-800 text-zinc-900 dark:text-zinc-100 text-[15px] px-4 py-3 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-800 dark:focus:ring-white focus:ring-offset-2 focus:ring-offset-zinc-50 dark:focus:ring-offset-zinc-950 transition-shadow"
                        x-bind:disabled="$wire.isProcessing"
                        x-on:input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 160) + 'px'"
                    ></textarea>
                    @error('input')
                        <p class="text-red-500 text-xs mt-1.5 px-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="absolute right-0 bottom-1 h-[48px] flex items-center">
                    <button
                        type="submit"
                        class="w-10 h-10 rounded-lg bg-zinc-800 dark:bg-white text-white dark:text-zinc-800 flex items-center justify-center transition-opacity hover:opacity-80 disabled:opacity-30 disabled:cursor-not-allowed"
                        x-bind:disabled="$wire.isProcessing"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                            <path d="M3.105 2.288a.75.75 0 0 0-.826.95l1.414 4.926A1.5 1.5 0 0 0 5.135 9.25h6.115a.75.75 0 0 1 0 1.5H5.135a1.5 1.5 0 0 0-1.442 1.086l-1.414 4.926a.75.75 0 0 0 .826.95 28.897 28.897 0 0 0 15.293-7.155.75.75 0 0 0 0-1.114A28.897 28.897 0 0 0 3.105 2.288Z" />
                        </svg>
                    </button>
                </div>
            </form>
            <p class="font-mono text-xs text-zinc-400 dark:text-zinc-600 text-center mt-3">&copy; {{ date('Y') }} James Gifford</p>
        </div>
    </div>

    @script
    <script>
        $wire.on('message-sent', () => {
            $nextTick(() => {
                const container = $refs.messagesContainer ?? document.querySelector('[x-ref="messagesContainer"]');
                if (container) {
                    container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
                }
            });
        });

        Livewire.hook('morph.updated', () => {
            $nextTick(() => {
                const container = $refs.messagesContainer ?? document.querySelector('[x-ref="messagesContainer"]');
                if (container) {
                    container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
                }
            });
        });
    </script>
    @endscript
</div>
