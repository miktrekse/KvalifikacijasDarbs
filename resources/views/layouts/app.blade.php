<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Welcome') · DiscStats</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=JetBrains+Mono:wght@500;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden">
    @hasSection('auth')
        <div class="ds-auth">
            <aside class="ds-auth__art">
                <a href="{{ route('login') }}" class="ds-brand">
                    <span class="ds-brand__name">Disc<span>Stats</span></span>
                </a>
                <img src="{{ asset('images/logo.png') }}" alt="Disc Golf Stats" class="ds-auth__logo">
                <div>
                    <p class="ds-eyebrow">Your game, measured</p>
                    <p class="ds-auth__quote mt-4">Every throw tells a story.<br><em>Read yours.</em></p>
                    <div class="ds-auth__chips">
                        <span>Shot-by-shot rounds</span>
                        <span>Tournaments</span>
                        <span>Course map</span>
                        <span>Drills</span>
                    </div>
                </div>
            </aside>
            <section class="ds-auth__panel">
                @yield('auth')
            </section>
        </div>
    @else
        @yield('content')
    @endif
</body>
</html>
