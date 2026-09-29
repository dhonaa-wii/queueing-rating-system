{{--
    Scrolls to and blinks the exact element a link points at. A link adds
    ?focus=key1,key2; every element carrying data-focus="<key>" for any of
    those keys blinks, and the first one found is scrolled to. An element
    inside a hidden tab names that tab's trigger in data-focus-reveal (a CSS
    selector), which is clicked first. The param is stripped from the URL
    afterwards so a reload doesn't blink again.

    Also exposes window.focusFlash(elements) for in-page use (the dashboard's
    Needs attention button).
--}}
<script>
    (function () {
        var timers = new WeakMap();

        function flash(elements) {
            elements = Array.prototype.slice.call(elements).filter(Boolean);
            if (! elements.length) return;

            elements[0].scrollIntoView({ behavior: 'smooth', block: 'center' });

            elements.forEach(function (el) {
                // data-focus-glow swaps the red blink for a border glow.
                var cls = el.hasAttribute('data-focus-glow') ? 'focus-glow' : 'focus-flash';
                el.classList.remove(cls);
                void el.offsetWidth; // restart the animation on a repeat
                el.classList.add(cls);
                clearTimeout(timers.get(el));
                timers.set(el, setTimeout(function () { el.classList.remove(cls); }, cls === 'focus-glow' ? 6000 : 3000));
            });
        }

        window.focusFlash = flash;

        var params = new URLSearchParams(window.location.search);
        var focus = params.get('focus');
        if (! focus) return;

        params.delete('focus');
        var query = params.toString();
        try {
            history.replaceState(null, '', window.location.pathname + (query ? '?' + query : '') + window.location.hash);
        } catch (e) {
            // Cosmetic only — the blink still runs.
        }

        window.addEventListener('load', function () {
            var targets = [];
            focus.split(',').forEach(function (key) {
                if (! key) return;
                document.querySelectorAll('[data-focus="' + CSS.escape(key) + '"]').forEach(function (el) {
                    targets.push(el);
                });
            });
            if (! targets.length) return;

            var revealed = false;
            targets.forEach(function (el) {
                var trigger = el.dataset.focusReveal ? document.querySelector(el.dataset.focusReveal) : null;
                if (trigger && ! trigger.classList.contains('active')) {
                    trigger.click();
                    revealed = true;
                }
            });

            // Give page scripts (tab-state restore, filters) and any tab
            // transition a moment to settle before measuring the scroll target.
            setTimeout(function () { flash(targets); }, revealed ? 450 : 250);
        });
    })();
</script>
