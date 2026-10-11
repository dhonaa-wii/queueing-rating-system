{{-- Shared compact card styles for the login and change-password pages. --}}
@push('styles')
    <style>
        .auth-wrap { display: flex; justify-content: center; padding-top: clamp(0.5rem, 4vh, 2.5rem); }
        .auth-card {
            width: 100%; max-width: 23rem;
            background: var(--brand-surface-alt); border-radius: 1rem;
            padding: clamp(1.1rem, 3vw, 1.5rem);
            box-shadow: 0 0.75rem 2rem rgba(0, 0, 0, 0.06);
            --auth-fs: clamp(0.8rem, 0.76rem + 0.15vw, 0.875rem);
            --auth-fs-sm: clamp(0.72rem, 0.7rem + 0.1vw, 0.78rem);
            font-size: var(--auth-fs);
        }
        .auth-head { display: flex; align-items: center; gap: 0.7rem; margin-bottom: 1rem; }
        .auth-head-icon {
            flex: 0 0 auto; width: 2.4rem; height: 2.4rem; border-radius: 0.7rem;
            display: grid; place-items: center;
            background: var(--brand-accent-tint); color: var(--brand-accent);
        }
        .auth-head-icon svg { width: 1.2rem; height: 1.2rem; }
        .auth-title { font-size: clamp(1rem, 0.95rem + 0.25vw, 1.15rem); font-weight: 600; margin: 0; line-height: 1.2; }
        .auth-sub { font-size: var(--auth-fs-sm); color: var(--brand-muted); margin: 0.15rem 0 0; }

        .auth-card .alert { font-size: var(--auth-fs-sm); padding: 0.5rem 0.7rem; margin-bottom: 0.85rem; border-radius: 0.6rem; }
        .auth-field { margin-bottom: 0.75rem; }
        .auth-field label { font-size: var(--auth-fs-sm); font-weight: 500; margin-bottom: 0.25rem; display: block; }
        .auth-input { position: relative; }
        .auth-input > svg {
            position: absolute; left: 0.65rem; top: 50%; transform: translateY(-50%);
            width: 0.95rem; height: 0.95rem; color: var(--brand-muted); pointer-events: none;
        }
        .auth-input .form-control {
            font-size: var(--auth-fs); padding: 0.42rem 2.2rem 0.42rem 2rem;
            border-radius: 0.6rem; background: var(--brand-surface);
        }
        .auth-eye {
            position: absolute; right: 0.3rem; top: 50%; transform: translateY(-50%);
            border: 0; background: transparent; color: var(--brand-muted);
            width: 1.8rem; height: 1.8rem; border-radius: 0.45rem;
            display: grid; place-items: center; cursor: pointer;
        }
        .auth-eye:hover, .auth-eye.is-on { color: var(--brand-accent); background: var(--brand-accent-tint); }
        .auth-eye svg { width: 0.95rem; height: 0.95rem; }

        .auth-card .btn { font-size: var(--auth-fs); padding: 0.45rem 0.8rem; border-radius: 0.6rem; }
        .auth-divider {
            display: flex; align-items: center; gap: 0.6rem; margin: 0.9rem 0;
            font-size: var(--auth-fs-sm); color: var(--brand-muted);
        }
        .auth-divider::before, .auth-divider::after { content: ''; flex: 1; border-top: 1px solid var(--brand-border); }
        .auth-link-btn {
            border: 0; background: none; padding: 0; font-size: var(--auth-fs-sm);
            color: var(--brand-muted); text-decoration: underline; text-underline-offset: 2px;
        }
        .auth-link-btn:hover { color: var(--brand-accent); }
    </style>
@endpush

@push('scripts')
    <script>
        document.querySelectorAll('[data-auth-eye]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var field = document.getElementById(btn.dataset.authEye);
                if (!field) return;
                var show = field.type === 'password';
                field.type = show ? 'text' : 'password';
                btn.classList.toggle('is-on', show);
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            });
        });
    </script>
@endpush
