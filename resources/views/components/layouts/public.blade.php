@props(['title' => null, 'metaDescription' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? "$title | " . config('app.name') : config('app.name') }}</title>
        @if($metaDescription)
            <meta name="description" content="{{ $metaDescription }}">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|jetbrains-mono:400,500" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 antialiased">
        {{-- Blueprint dot grid background --}}
        <div class="fixed inset-0 -z-[5] opacity-[0.03] dark:opacity-[0.06]"
             style="background-image: radial-gradient(circle, currentColor 1px, transparent 1px); background-size: 24px 24px;">
        </div>

        {{-- Navigation --}}
        @include('partials.nav')

        <main class="pt-20">
            {{ $slot }}
        </main>


        {{-- Dark mode toggle (fixed, above input area) --}}
        <div class="fixed bottom-6 left-6 z-50" x-data>
            <flux:button
                variant="subtle"
                square
                x-on:click="$flux.dark = ! $flux.dark"
                aria-label="Toggle dark mode"
                class="!size-10 rounded-full border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-sm hover:border-zinc-400 dark:hover:border-zinc-600"
            >
                <flux:icon.sun x-show="$flux.dark" variant="mini" class="!size-4" />
                <flux:icon.moon x-show="! $flux.dark" variant="mini" class="!size-4" />
            </flux:button>
        </div>

        @fluxScripts
    </body>
</html>
