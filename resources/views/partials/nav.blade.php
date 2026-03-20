<header class="fixed top-0 inset-x-0 z-50 bg-zinc-50/80 dark:bg-zinc-950/80 backdrop-blur-md" x-data="{ open: false }">
    <nav class="mx-auto max-w-5xl flex items-center justify-between px-6 py-5">
        {{-- Logo --}}
        <a href="/" class="font-mono text-lg font-medium tracking-tight text-zinc-900 dark:text-zinc-100">
            jg<span class="text-emerald-600 dark:text-emerald-400">.ai</span>
        </a>

        {{-- Desktop nav --}}
        <div class="hidden sm:flex items-center gap-6">
            <a href="https://jamesgifford.com" target="_blank" rel="noopener" class="font-mono text-xs text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">jamesgifford.com</a>
            <a href="https://linkedin.com/in/jamesgifford" target="_blank" rel="noopener" class="font-mono text-xs text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors">linkedin</a>
        </div>

        {{-- Mobile hamburger --}}
        <button x-on:click="open = !open" class="sm:hidden p-1 text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors" aria-label="Toggle menu">
            <svg x-show="!open" class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
            <svg x-show="open" x-cloak class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </nav>

    {{-- Mobile menu --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         class="sm:hidden border-t border-zinc-200 dark:border-zinc-800">
        <div class="mx-auto max-w-5xl flex flex-col gap-1 px-6 py-4">
            <a href="https://jamesgifford.com" target="_blank" rel="noopener" x-on:click="open = false" class="font-mono text-sm text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors py-2">jamesgifford.com</a>
            <a href="https://linkedin.com/in/jamesgifford" target="_blank" rel="noopener" x-on:click="open = false" class="font-mono text-sm text-zinc-500 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 transition-colors py-2">linkedin</a>
        </div>
    </div>
</header>
