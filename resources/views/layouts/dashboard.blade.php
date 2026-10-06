<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · DiscStats</title>
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=JetBrains+Mono:wght@500;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden">
    @php
        $user = Auth::user();
        $isGuest = $user->isGuest();
        $roleLabel = $user->isAdmin() ? 'Admin' : ($user->isVerified() ? 'Verified' : ($isGuest ? 'Guest' : 'Player'));
        $navItems = $isGuest ? [
            ['url' => url('/dashboard'), 'label' => 'Dashboard', 'active' => request()->is('dashboard'),
                'icon' => '<path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z"/>'],
            ['url' => route('exercises.index'), 'label' => 'Exercises', 'active' => request()->is('exercises*'),
                'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5zM4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/>'],
            ['url' => url('/competitions'), 'label' => 'Competitions', 'active' => request()->is('competitions*'),
                'icon' => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0zM17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/>'],
            ['url' => route('players.index'), 'label' => 'Players', 'active' => request()->is('players*', 'profiles*'),
                'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>'],
            ['url' => route('courses.index'), 'label' => 'Courses Map', 'active' => request()->is('courses*'),
                'icon' => '<path d="M9 3 3 6v15l6-3 6 3 6-3V3l-6 3zM9 3v15M15 6v15"/>'],
        ] : [
            ['url' => url('/dashboard'), 'label' => 'Dashboard', 'active' => request()->is('dashboard', 'admin*'),
                'icon' => '<path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z"/>'],
            ['url' => route('exercises.index'), 'label' => 'Exercises', 'active' => request()->is('exercises/index', 'exercises/view*', 'exercises/create', 'exercises/edit*'),
                'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5zM4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5"/>'],
            ['url' => url('/exercises/saved'), 'label' => 'My Saved', 'active' => request()->is('exercises/saved'),
                'icon' => '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>'],
            ['url' => url('/competitions'), 'label' => 'Competitions', 'active' => request()->is('competitions*'),
                'icon' => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0zM17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3"/>'],
            ['url' => route('training.index'), 'label' => 'Training Rounds', 'active' => request()->is('training*'),
                'icon' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>'],
            ['url' => route('players.index'), 'label' => 'Players', 'active' => request()->is('players*') || (request()->is('profiles*') && request()->route('user')?->isNot($user)),
                'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>'],
            ['url' => route('courses.index'), 'label' => 'Courses Map', 'active' => request()->is('courses*'),
                'icon' => '<path d="M9 3 3 6v15l6-3 6 3 6-3V3l-6 3zM9 3v15M15 6v15"/>'],
        ];
    @endphp

    <nav class="ds-nav">
        <div class="ds-nav__inner">
            <a href="/dashboard" class="ds-brand">
                <img src="{{ asset('images/logo.png') }}" alt="">
                <span>
                    <span class="ds-brand__name">Disc<span>Stats</span></span>
                    <span class="ds-brand__tag">Throw · Track · Improve</span>
                </span>
            </a>

            <div class="ds-links">
                @foreach($navItems as $item)
                    <a href="{{ $item['url'] }}" @class(['ds-link', 'is-active' => $item['active']])>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $item['icon'] !!}</svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>

            <div class="ds-user">
                <a href="{{ $isGuest ? url('/dashboard') : route('profile.show', $user) }}" class="ds-user__chip" title="Your profile">
                    <span class="ds-avatar">
                        @if($user->avatar)
                            <img src="{{ asset('storage/' . $user->avatar) }}" alt="">
                        @else
                            <span>{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                        @endif
                    </span>
                    <span>
                        <span class="ds-user__name">{{ $user->name }}</span>
                        <span @class(['ds-user__role', 'is-admin' => $user->isAdmin()])>{{ $roleLabel }}</span>
                    </span>
                </a>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="ds-logout" title="{{ $isGuest ? 'Exit guest mode' : 'Log out' }}" aria-label="{{ $isGuest ? 'Exit guest mode' : 'Log out' }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    </button>
                </form>
            </div>

            <button type="button" class="ds-burger" id="mobile-menu-btn" aria-label="Open menu" aria-expanded="false">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h10M4 17h16"/></svg>
            </button>
        </div>

        <div class="ds-mobile" id="mobile-menu" style="display: none;">
            <a href="{{ $isGuest ? url('/dashboard') : route('profile.show', $user) }}" class="mb-4 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 p-3 text-white">
                <span class="ds-avatar" style="width: 2.75rem; height: 2.75rem;">
                    @if($user->avatar)
                        <img src="{{ asset('storage/' . $user->avatar) }}" alt="">
                    @else
                        <span>{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                    @endif
                </span>
                <span>
                    <span class="block font-bold">{{ $user->name }}</span>
                    <span @class(['ds-user__role', 'is-admin' => $user->isAdmin()])>{{ $roleLabel }}</span>
                </span>
            </a>
            <div class="space-y-1">
                @foreach($navItems as $item)
                    <a href="{{ $item['url'] }}" @class(['ds-link', 'is-active' => $item['active']])>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $item['icon'] !!}</svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
            <form action="{{ route('logout') }}" method="POST" class="mt-6 border-t border-white/10 pt-4">
                @csrf
                <button type="submit" class="ds-btn ds-btn--ghost w-full">{{ $isGuest ? 'Exit guest mode' : 'Log out' }}</button>
            </form>
        </div>
    </nav>

    <main class="mx-auto w-full max-w-7xl overflow-x-hidden px-3 py-5 sm:px-6 sm:py-8">
        @if($isGuest)
            <div class="ds-guest-bar">
                <span class="ds-guest-bar__icon">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </span>
                <p class="min-w-0 flex-1 text-sm">
                    <strong class="font-bold text-ink">You're browsing as a guest.</strong>
                    <span class="text-gray-600">Sign up to log rounds, save drills and register for competitions.</span>
                </p>
                <div class="flex shrink-0 gap-2">
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="ds-btn ds-btn--line !py-2">Sign in</button>
                    </form>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <input type="hidden" name="then" value="register">
                        <button type="submit" class="ds-btn ds-btn--flight !py-2">Sign up free</button>
                    </form>
                </div>
            </div>
        @endif

        @if(session('success'))
            <div class="ds-flash ds-flash--ok" role="status">
                <span class="ds-flash__icon">✓</span>
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="ds-flash ds-flash--err" role="alert">
                <span class="ds-flash__icon">!</span>
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 pb-8 pt-2 font-mono text-[0.65rem] uppercase tracking-[0.2em] text-gray-400 sm:px-6">
        <span>DiscStats · {{ now()->year }}</span>
        <span>Every throw counts</span>
    </footer>

    <script>
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');

        mobileMenuBtn.addEventListener('click', () => {
            const open = mobileMenu.style.display === 'none';
            mobileMenu.style.display = open ? 'block' : 'none';
            mobileMenuBtn.setAttribute('aria-expanded', open);
            document.body.style.overflow = open ? 'hidden' : '';
        });
    </script>
    @stack('scripts')
</body>
</html>
