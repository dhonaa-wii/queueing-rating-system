<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    @include('partials.theme-head')
    <style>
        .settings-icon-wrap {
            position: relative;
        }

        .settings-icon-btn-pulse {
            border-color: var(--brand-accent);
            color: var(--brand-accent);
            animation: settings-icon-pulse 1.7s ease-out infinite;
        }

        @keyframes settings-icon-pulse {
            0% { box-shadow: 0 0 0 0 rgba(193, 113, 46, 0.45); }
            70% { box-shadow: 0 0 0 9px rgba(193, 113, 46, 0); }
            100% { box-shadow: 0 0 0 0 rgba(193, 113, 46, 0); }
        }

        [data-bs-theme="dark"] .settings-icon-btn-pulse {
            animation-name: settings-icon-pulse-dark;
        }

        @keyframes settings-icon-pulse-dark {
            0% { box-shadow: 0 0 0 0 rgba(224, 138, 69, 0.45); }
            70% { box-shadow: 0 0 0 9px rgba(224, 138, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(224, 138, 69, 0); }
        }

        .settings-coachmark {
            position: absolute;
            top: calc(100% + 14px);
            right: 0.4rem;
            z-index: 1050;
            animation: settings-coachmark-bounce 1.7s ease-in-out infinite;
            pointer-events: none;
        }

        .settings-coachmark-bubble {
            position: relative;
            display: inline-block;
            background-color: var(--brand-accent);
            color: var(--brand-accent-contrast);
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.4rem 0.75rem;
            border-radius: 0.5rem;
            box-shadow: var(--brand-shadow);
            white-space: nowrap;
        }

        .settings-coachmark-bubble::before {
            content: "";
            position: absolute;
            top: -6px;
            right: 12px;
            width: 0;
            height: 0;
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-bottom: 6px solid var(--brand-accent);
        }

        @keyframes settings-coachmark-bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
    </style>
    @stack('styles')
</head>
<body>
    <nav class="navbar navbar-expand border-bottom" style="border-color: var(--brand-border) !important;">
        <div class="container d-flex align-items-center justify-content-between">
            @hasSection('navbar-inner')
                @yield('navbar-inner')
            @else
                <span class="navbar-brand-mark fs-4 mb-0">@hasSection('navbar-brand')@yield('navbar-brand')@else{{ 'ARPQRS' }}@endif</span>

                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('landing') }}" class="small text-decoration-none text-brand-muted">Home</a>
                    @if (! auth()->check() || request()->routeIs('password.change'))
                        @include('partials.theme-toggle-button')
                    @endif
                    @auth
                        @unless (request()->routeIs('password.change'))
                            @php $needsPasswordChange = auth()->user()->mustChangePassword(); @endphp
                            <span class="settings-icon-wrap">
                                <button type="button" id="settings-icon-btn" class="settings-icon-btn {{ $needsPasswordChange ? 'settings-icon-btn-pulse' : '' }}" data-bs-toggle="modal" data-bs-target="#settings-modal" aria-label="Settings">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                                </button>

                                @if ($needsPasswordChange)
                                    <span class="settings-coachmark" id="settings-coachmark">
                                        <span class="settings-coachmark-bubble">Change your password here</span>
                                    </span>
                                @endif
                            </span>
                        @endunless
                    @endauth
                </div>
            @endif
        </div>
    </nav>

    <div class="@yield('container-class', 'container') py-4">
        @yield('content')
    </div>

    @auth
        @unless (request()->routeIs('password.change'))
            @include('partials.settings-modal')
        @endunless
    @endauth

    @include('partials.theme-toggle-script')
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        (function () {
            var modalEl = document.getElementById('settings-modal');
            var coachmark = document.getElementById('settings-coachmark');
            var iconBtn = document.getElementById('settings-icon-btn');

            if (!modalEl || !coachmark || !iconBtn) return;

            modalEl.addEventListener('show.bs.modal', function () {
                coachmark.style.display = 'none';
                iconBtn.classList.remove('settings-icon-btn-pulse');
            });

            modalEl.addEventListener('hidden.bs.modal', function () {
                coachmark.style.display = '';
                iconBtn.classList.add('settings-icon-btn-pulse');
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
