<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="h-screen overflow-hidden">
        <livewire:chat />
        @fluxScripts
    </body>
</html>
