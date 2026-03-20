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


        @fluxScripts
    </body>
</html>
