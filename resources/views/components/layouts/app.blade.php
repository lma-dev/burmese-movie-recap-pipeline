<!DOCTYPE html>
<html lang="en" class="bg-zinc-50 text-zinc-900 antialiased">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Burmese Movie Recap Pipeline' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=Noto+Sans+Myanmar:wght@400;500&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
    @livewireStyles
</head>
<body class="min-h-screen flex flex-col bg-zinc-50 text-zinc-900">
    <x-app-header />

    <main class="flex-1 flex flex-col min-h-0">
        {{ $slot }}
    </main>

    @livewireScripts
    @fluxScripts
</body>
</html>
