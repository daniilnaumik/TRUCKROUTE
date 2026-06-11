<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'TruckRoute')</title>
    <script>
        /* Prevent flash of wrong theme — runs before CSS is applied */
        (function () {
            var t = localStorage.getItem('theme');
            if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
    @stack('styles')
</head>
<body class="page-{{ request()->route()?->getName() ?? 'home' }}" data-auth="{{ auth()->check() ? 'user' : 'guest' }}">
    <header class="site-header">
        <a class="logo" href="{{ route('home') }}">TRUCKROUTE</a>
        <nav class="main-nav" aria-label="Основная навигация">
            <div class="nav-drawer" id="navDrawer">
                <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">главная</a>
                <a class="{{ request()->routeIs('news') ? 'active' : '' }}" href="{{ route('news') }}">новости</a>
                <a class="{{ request()->routeIs('routes') ? 'active' : '' }}" href="{{ route('routes') }}">маршруты</a>
                @auth
                    <a class="{{ request()->routeIs('settings') ? 'active' : '' }}" href="{{ route('settings') }}">настройки</a>
                    <a class="{{ request()->routeIs('profile') ? 'active' : '' }}" href="{{ route('profile') }}">профиль</a>
                    @if(auth()->user()->role === 'admin')
                        <a class="{{ request()->routeIs('admin') ? 'active' : '' }}" href="{{ route('admin') }}">админ</a>
                    @endif

                    <div class="nav-notifications" id="navNotifications">
                        <button class="nav-notifications__btn" id="notifBtn" type="button" aria-label="Уведомления">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            <span class="nav-notifications__badge" id="notifBadge" hidden>0</span>
                        </button>
                        <div class="nav-notifications__dropdown" id="notifDropdown" hidden>
                            <div class="nav-notifications__header">
                                <span>Уведомления</span>
                                <button type="button" id="notifReadAll">отметить все</button>
                            </div>
                            <ul class="nav-notifications__list" id="notifList">
                                <li class="nav-notifications__empty">Загрузка...</li>
                            </ul>
                        </div>
                    </div>

                    <form class="nav-logout" method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">выйти</button>
                    </form>
                @else
                    <a class="{{ request()->routeIs('login') ? 'active' : '' }}" href="{{ route('login') }}">войти</a>
                @endauth
            </div>

            @if(config('app.debug'))
            <div class="nav-test-switcher" id="navTestSwitcher">
                <button class="nav-test-switcher__btn" id="testSwitcherBtn" type="button" title="Dev: быстрый вход">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span class="nav-test-switcher__label">тест</span>
                </button>
                <div class="nav-test-switcher__dropdown" id="testSwitcherDropdown" hidden>
                    <div class="nav-test-switcher__header">тестовые аккаунты</div>
                    @php
                        $devAccounts = [
                            ['email' => 'driver@truckroute.local', 'name' => 'Даниил Наумик', 'role' => 'driver'],
                            ['email' => 'admin@truckroute.local',  'name' => 'Администратор', 'role' => 'admin'],
                        ];
                    @endphp
                    @foreach($devAccounts as $devAccount)
                        @php $isCurrent = auth()->check() && auth()->user()->email === $devAccount['email']; @endphp
                        <a class="nav-test-switcher__item{{ $isCurrent ? ' is-active' : '' }}"
                           href="{{ route('dev.switch', ['email' => $devAccount['email']]) }}">
                            <span class="nav-test-switcher__name">{{ $devAccount['name'] }}</span>
                            <span class="nav-test-switcher__role">{{ $devAccount['role'] }}</span>
                            @if($isCurrent)
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;color:var(--accent)"><polyline points="20 6 9 17 4 12"/></svg>
                            @endif
                        </a>
                    @endforeach
                    @auth
                    <div class="nav-test-switcher__footer">
                        <form method="POST" action="{{ route('logout') }}" style="margin:0">
                            @csrf
                            <button type="submit" class="nav-test-switcher__logout">выйти из аккаунта</button>
                        </form>
                    </div>
                    @endauth
                </div>
            </div>
            @endif
            <button class="nav-theme-toggle" id="themeToggle" type="button" aria-label="Переключить тему">
                <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
            <button class="nav-hamburger" id="navHamburger" type="button" aria-label="Меню" aria-expanded="false" aria-controls="navDrawer">
                <svg class="icon-menu" width="20" height="14" viewBox="0 0 20 14" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                    <path d="M0 1h20M0 7h20M0 13h20"/>
                </svg>
                <svg class="icon-close" width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
                    <path d="M2 2l14 14M16 2L2 16"/>
                </svg>
            </button>
        </nav>
    </header>

    <main>
        @yield('content')
    </main>

    @guest
        <div class="auth-modal" id="authModal" aria-hidden="true">
            <div class="auth-modal__backdrop" data-auth-close></div>
            <div class="auth-modal__panel" role="dialog" aria-modal="true" aria-labelledby="authModalTitle">
                <button class="auth-modal__close" type="button" data-auth-close>закрыть</button>
                <span class="badge">требуется вход</span>
                <h2 id="authModalTitle">Войдите в аккаунт</h2>
                <p class="lead">Чтобы построить и сохранить маршрут, нужно войти в профиль TruckRoute. Так система сможет привязать маршрут к вашему транспорту, настройкам и истории поездок.</p>
                <div class="actions">
                    <a class="btn" href="{{ route('login') }}">Войти</a>
                    <a class="btn outline" href="{{ route('register') }}">Создать аккаунт</a>
                </div>
            </div>
        </div>
    @endguest

    <footer class="site-footer">
        <div>TRUCKROUTE</div>
        <div>Информационная система поддержки маршрутизации грузового транспорта</div>
    </footer>

    <script>
    /* Theme toggle */
    (function () {
        var btn = document.getElementById('themeToggle');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            if (isDark) {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('theme', 'light');
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
            }
        });
    })();
    </script>
    <script src="{{ asset('assets/js/app.js') }}?v={{ filemtime(public_path('assets/js/app.js')) }}"></script>
    <script src="{{ asset('assets/js/toast.js') }}"></script>
    <script src="{{ asset('assets/js/geocode.js') }}"></script>
    @stack('scripts')

    @auth
    <script>
    (function () {
        var btn = document.getElementById('notifBtn');
        var dropdown = document.getElementById('notifDropdown');
        var badge = document.getElementById('notifBadge');
        var list = document.getElementById('notifList');
        var readAllBtn = document.getElementById('notifReadAll');
        if (!btn) return;

        function getCsrf() {
            var m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
            return m ? decodeURIComponent(m[1]) : (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        }

        function loadNotifications() {
            fetch('/api/v1/notifications?per_page=8', {
                headers: { 'Accept': 'application/json', 'X-XSRF-TOKEN': getCsrf() },
                credentials: 'same-origin',
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var unread = data.unread_count || 0;
                badge.textContent = unread > 9 ? '9+' : unread;
                badge.hidden = unread === 0;
                renderList(data.data || []);
            })
            .catch(function () {});
        }

        function renderList(items) {
            if (!items.length) {
                list.innerHTML = '<li class="nav-notifications__empty">Нет уведомлений</li>';
                return;
            }
            list.innerHTML = items.map(function (n) {
                var read = n.read_at ? ' is-read' : '';
                var title = (n.data && n.data.title) ? n.data.title : 'Уведомление';
                var body = (n.data && n.data.body) ? n.data.body : '';
                return '<li class="nav-notifications__item' + read + '" data-notif-id="' + n.id + '">'
                    + '<strong>' + title + '</strong>'
                    + (body ? '<span>' + body + '</span>' : '')
                    + '</li>';
            }).join('');
        }

        function markRead(id) {
            fetch('/api/v1/notifications/' + id + '/read', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-XSRF-TOKEN': getCsrf() },
                credentials: 'same-origin',
            }).then(function () { loadNotifications(); }).catch(function () {});
        }

        btn.addEventListener('click', function () {
            var hidden = dropdown.hidden;
            dropdown.hidden = !hidden;
            if (!hidden) return;
            loadNotifications();
        });

        list.addEventListener('click', function (e) {
            var item = e.target.closest('[data-notif-id]');
            if (item && !item.classList.contains('is-read')) {
                markRead(item.dataset.notifId);
            }
        });

        readAllBtn.addEventListener('click', function () {
            fetch('/api/v1/notifications/read-all', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-XSRF-TOKEN': getCsrf() },
                credentials: 'same-origin',
            }).then(function () { loadNotifications(); }).catch(function () {});
        });

        document.addEventListener('click', function (e) {
            if (!document.getElementById('navNotifications').contains(e.target)) {
                dropdown.hidden = true;
            }
        });

        loadNotifications();
        setInterval(loadNotifications, 60000);
    })();
    </script>
    @endauth
    <script>
    /* Dev test-account switcher */
    (function () {
        var wrap = document.getElementById('navTestSwitcher');
        if (!wrap) return;
        var btn      = document.getElementById('testSwitcherBtn');
        var dropdown = document.getElementById('testSwitcherDropdown');

        btn.addEventListener('click', function () {
            dropdown.hidden = !dropdown.hidden;
        });

        document.addEventListener('click', function (e) {
            if (!wrap.contains(e.target)) dropdown.hidden = true;
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') dropdown.hidden = true;
        });
    })();
    </script>
    <script>
    /* Hamburger drawer toggle */
    (function () {
        var hamburger = document.getElementById('navHamburger');
        if (!hamburger) return;
        var header = hamburger.closest('.site-header');
        var drawer = document.getElementById('navDrawer');

        function close() {
            header.classList.remove('nav-open');
            hamburger.setAttribute('aria-expanded', 'false');
        }

        hamburger.addEventListener('click', function () {
            var isOpen = header.classList.toggle('nav-open');
            hamburger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        drawer.addEventListener('click', function (e) {
            if (e.target.tagName === 'A') close();
        });

        document.addEventListener('click', function (e) {
            if (!header.contains(e.target)) close();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });
    })();
    </script>
</body>
</html>
