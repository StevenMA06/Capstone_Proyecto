<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Mi panel') · LUNNY’S PERU R.2.D.</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=5">
    <script src="{{ asset('js/login.js') }}" defer></script>
    @include('layouts.session-monitor')
    @stack('styles')
    <script src="{{ asset('js/validation-es.js') }}?v=1" defer></script>
</head>
<body class="@yield('body-class')">
    <div class="page-decoration" aria-hidden="true"></div>
    <main>@yield('content')</main>
    <footer><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/></svg> © {{ date('Y') }} LUNNY’S PERU R.2.D. Todos los derechos reservados.</footer>
</body>
</html>
