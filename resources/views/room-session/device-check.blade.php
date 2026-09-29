{{--
    Diagnostic page for the physical room tablets. Deliberately standalone —
    no layout, no Bootstrap, no custom properties, no flex or grid — so it
    renders on a browser too old for the rest of the app and can report why.

    Reached at /room-session/device-check. Safe to leave in place: it is
    read-only, shows nothing about the event or any account, and is the fastest
    way to tell a layout bug apart from an unsupported-CSS one on a device that
    can't be inspected remotely.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Device Check</title>
</head>
<body style="margin:0;padding:16px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#222;background:#fff">
    <h1 style="font-size:20px;margin:0 0 12px">Device Check</h1>

    <table id="out" border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%">
        <tr><td colspan="2">Reading&hellip;</td></tr>
    </table>

    <p style="margin:14px 0 0;font-size:13px;color:#555">
        Screenshot this whole page.
    </p>

    <script>
    (function () {
        var rows = [];

        function add(label, value) {
            rows.push([label, String(value)]);
        }

        function supports(prop, value) {
            try {
                if (window.CSS && CSS.supports) {
                    return CSS.supports(prop, value) ? 'YES' : 'NO';
                }
            } catch (e) {}
            // No CSS.supports at all means a browser older than every feature
            // below, so report it once rather than per row.
            return 'unknown (no CSS.supports)';
        }

        add('Browser (user agent)', navigator.userAgent);
        add('Screen width (CSS px)', document.documentElement.clientWidth);
        add('Screen height (CSS px)', document.documentElement.clientHeight);
        add('window.innerWidth', window.innerWidth);
        add('Device pixel ratio', window.devicePixelRatio || 1);
        add('Physical px (w x h)', screen.width + ' x ' + screen.height);
        add('CSS flexbox', supports('display', 'flex'));
        add('CSS grid', supports('display', 'grid'));
        add('gap in flexbox', supports('row-gap', '1rem'));
        add('clamp()', supports('font-size', 'clamp(1rem, 2vw, 3rem)'));
        add('min() / max()', supports('width', 'min(10px, 2vw)'));
        add('CSS variables', supports('--x', '1px'));
        add('position: sticky', supports('position', 'sticky'));
        add('color-mix()', supports('color', 'color-mix(in srgb, red 50%, blue)'));
        add(':has()', supports('selector(:has(*))', '') === 'YES' ? 'YES' : (function () {
            try { document.querySelector(':has(*)'); return 'YES'; } catch (e) { return 'NO'; }
        })());
        add('container queries', supports('container-type', 'inline-size'));

        var html = '';
        for (var i = 0; i < rows.length; i++) {
            html += '<tr><td style="font-weight:bold;white-space:nowrap">' + rows[i][0]
                 + '</td><td style="word-break:break-all">' + rows[i][1] + '</td></tr>';
        }
        document.getElementById('out').innerHTML = html;
    })();
    </script>
</body>
</html>
