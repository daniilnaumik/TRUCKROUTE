<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>TruckRoute</title>
    {{-- Prevent flash of wrong theme --}}
    <script>
        (function () {
            var t = localStorage.getItem('theme');
            if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        })();
    </script>
    {{-- Design system CSS (static, not processed by Vite) --}}
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
    {{-- Vue app (Vite) --}}
    @vite(['resources/js/app.js'])
</head>
<body class="page-spa" data-auth="{{ auth()->check() ? 'user' : 'guest' }}">
    <div id="app"></div>

    {{-- Pass server-side flags to JS --}}
    <script>
        window.__APP_DEBUG__ = {{ config('app.debug') ? 'true' : 'false' }};
        window.__DEMO_ACCOUNTS_ENABLED__ = {{ config('demo.accounts_enabled') ? 'true' : 'false' }};
        window.__APP_URL__   = '{{ config('app.url') }}';
        window.__YANDEX_JS_API_KEY__ = @json(config('geo.tiles.yandex.api_key'));
    </script>
</body>
</html>
