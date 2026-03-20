@props(['title' => null, 'metaDescription' => null])

@php
    $pageTitle = $title ? "$title | " . config('app.name') : config('app.name');
    $ogImage = asset('og-image.png');
    $canonicalUrl = url()->current();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $pageTitle }}</title>
        @if($metaDescription)
            <meta name="description" content="{{ $metaDescription }}">
        @endif
        <link rel="canonical" href="{{ $canonicalUrl }}">

        {{-- Open Graph --}}
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $pageTitle }}">
        @if($metaDescription)
            <meta property="og:description" content="{{ $metaDescription }}">
        @endif
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:site_name" content="{{ config('app.name') }}">

        {{-- Twitter Card --}}
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $pageTitle }}">
        @if($metaDescription)
            <meta name="twitter:description" content="{{ $metaDescription }}">
        @endif
        <meta name="twitter:image" content="{{ $ogImage }}">

        {{-- Favicons --}}
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

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
