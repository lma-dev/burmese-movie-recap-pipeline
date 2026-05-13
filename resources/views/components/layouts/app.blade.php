<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Burmese Movie Recap Pipeline' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <div class="app-shell">
        {{-- The SVG icon sprite (re-used everywhere via <use href="#i-..."/>) --}}
        @include('partials.icon-sprite')

        {{ $slot }}
    </div>

    @livewireScripts
</body>
</html>
