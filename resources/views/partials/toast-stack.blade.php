{{--
    System-wide "popping" notification — the single notification style used
    everywhere (replaces the old long-lived `alert-success`/`alert-danger`
    banners that used to sit at the top of every page until the next
    navigation). Included once per layout; any page/module can also call
    `window.showAppToast(message, isError)` directly for its own AJAX
    success/error feedback instead of keeping a separate toast stack.

    The `.app-toast`/`.app-toast-stack` CSS lives in partials/theme-head.blade.php,
    not here — this partial is included from inside <main>, which (per Blade's
    @extends evaluation order) renders AFTER the layout's own <head> has
    already printed @stack('styles'), so a @push('styles') from here would
    silently never reach <head> and the toast would render as unstyled plain
    text. theme-head.blade.php is a plain <style> block included directly in
    <head>, so it's always in time.
--}}
{{-- The flash is also carried as data so partials/soft-submit-script can
     read a saved page's outcome before deciding to swap it in. --}}
<div id="app-toast-stack" class="app-toast-stack"
     data-flash-status="{{ session('status') }}"
     data-flash-error="{{ session('error') ?: ($errors->any() ? $errors->first() : '') }}"></div>

@push('scripts')
    <script>
        window.showAppToast = window.showAppToast || function (message, isError) {
            var stack = document.getElementById('app-toast-stack');
            if (!stack || !message) return;
            var toast = document.createElement('div');
            toast.className = 'app-toast' + (isError ? ' is-error' : '');
            toast.textContent = message;
            stack.appendChild(toast);
            setTimeout(function () {
                toast.style.transition = 'opacity 0.2s ease';
                toast.style.opacity = '0';
                setTimeout(function () { toast.remove(); }, 200);
            }, 3200);
        };

        @if (session('status'))
            window.showAppToast(@json(session('status')), false);
        @endif
        @if (session('error'))
            window.showAppToast(@json(session('error')), true);
        @endif
        @if ($errors->any())
            window.showAppToast(@json($errors->first()), true);
        @endif
    </script>
@endpush
