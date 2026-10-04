{{-- Help Center behaviour: search over chapters, topics and FAQ questions;
     scroll-spy for the contents sidebar; the phone contents drawer; and
     print preparation (every FAQ answer opened so the PDF carries them). --}}
<script>
(function () {
    var chapters = Array.prototype.slice.call(document.querySelectorAll('[data-chapter]'));
    var topics = Array.prototype.slice.call(document.querySelectorAll('.hc-topic[id]'));
    var faqs = Array.prototype.slice.call(document.querySelectorAll('.hc-faq details'));

    /* ---------- Search index ---------- */
    var index = [];
    topics.forEach(function (topic) {
        var chapter = topic.closest('[data-chapter]');
        var heading = topic.querySelector('h3');
        index.push({
            el: topic,
            title: heading ? heading.textContent.trim() : '',
            path: chapter ? chapter.getAttribute('data-search-title') : '',
            body: topic.textContent.replace(/\s+/g, ' ').toLowerCase(),
            kind: 'topic'
        });
    });
    faqs.forEach(function (item, i) {
        if (!item.id) item.id = 'faq-' + (i + 1);
        var summary = item.querySelector('.hc-faq-text');
        var group = item.closest('.hc-topic');
        index.push({
            el: item,
            title: summary ? summary.textContent.trim() : '',
            path: 'FAQ' + (group && group.querySelector('h3') ? ' · ' + group.querySelector('h3').textContent.trim() : ''),
            body: item.textContent.replace(/\s+/g, ' ').toLowerCase(),
            kind: 'faq'
        });
    });

    var input = document.getElementById('hc-search');
    var results = document.getElementById('hc-search-results');
    var active = -1;
    var current = [];

    function escapeHtml(s) {
        return s.replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    }
    function highlight(text, words) {
        var out = escapeHtml(text);
        words.forEach(function (w) {
            if (w.length < 2) return;
            out = out.replace(new RegExp('(' + w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig'), '<mark class="hc-hit">$1</mark>');
        });
        return out;
    }
    function search(q) {
        var words = q.toLowerCase().split(/\s+/).filter(Boolean);
        if (!words.length) return [];
        return index.map(function (item) {
            var title = item.title.toLowerCase();
            var score = 0;
            for (var i = 0; i < words.length; i++) {
                var w = words[i];
                if (item.body.indexOf(w) === -1) return null;
                if (title.indexOf(w) !== -1) score += 10;
                score += Math.min(5, item.body.split(w).length - 1);
            }
            if (item.kind === 'faq') score += 1;
            return { item: item, score: score };
        }).filter(Boolean).sort(function (a, b) { return b.score - a.score; }).slice(0, 12).map(function (r) { return r.item; });
    }
    var topicIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M16 13H8M16 17H8"/></svg>';
    var faqIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/></svg>';

    function render() {
        var q = input.value.trim();
        if (!q) { results.classList.remove('is-open'); return; }
        current = search(q);
        active = current.length ? 0 : -1;
        var words = q.split(/\s+/);
        results.innerHTML = current.length
            ? current.map(function (item, i) {
                return '<button type="button" class="hc-search-result' + (i === active ? ' is-active' : '') + '" data-index="' + i + '" role="option">'
                    + (item.kind === 'faq' ? faqIcon : topicIcon)
                    + '<span><span class="hc-search-result-title">' + highlight(item.title, words) + '</span><br><span class="hc-search-result-path">' + escapeHtml(item.path) + '</span></span></button>';
            }).join('')
            : '<div class="hc-search-empty">No matches for “' + escapeHtml(q) + '”. Try another word, or browse the FAQ.</div>';
        results.classList.add('is-open');
    }
    function go(item) {
        results.classList.remove('is-open');
        input.blur();
        if (item.kind === 'faq') item.el.open = true;
        closeToc();
        item.el.scrollIntoView({ block: 'start' });
        item.el.animate && item.el.animate(
            [{ boxShadow: '0 0 0 3px rgba(193,113,46,0.45)' }, { boxShadow: '0 0 0 3px rgba(193,113,46,0)' }],
            { duration: 1600, easing: 'ease-out' }
        );
    }
    function setActive(i) {
        var buttons = results.querySelectorAll('.hc-search-result');
        buttons.forEach(function (b, j) { b.classList.toggle('is-active', j === i); });
        if (buttons[i]) buttons[i].scrollIntoView({ block: 'nearest' });
        active = i;
    }

    input.addEventListener('input', render);
    input.addEventListener('focus', function () { if (input.value.trim()) render(); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); if (current.length) setActive((active + 1) % current.length); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); if (current.length) setActive((active - 1 + current.length) % current.length); }
        else if (e.key === 'Enter') { e.preventDefault(); if (current[active]) go(current[active]); }
        else if (e.key === 'Escape') { input.value = ''; results.classList.remove('is-open'); input.blur(); }
    });
    results.addEventListener('mousedown', function (e) {
        var btn = e.target.closest('.hc-search-result');
        if (!btn) return;
        e.preventDefault();
        go(current[+btn.getAttribute('data-index')]);
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.hc-search')) results.classList.remove('is-open');
    });
    document.addEventListener('keydown', function (e) {
        var tag = (document.activeElement && document.activeElement.tagName) || '';
        if (e.key === '/' && tag !== 'INPUT' && tag !== 'TEXTAREA') { e.preventDefault(); input.focus(); input.select(); }
    });

    /* ---------- Scroll-spy ---------- */
    var tocChapters = {};
    document.querySelectorAll('[data-toc-chapter]').forEach(function (li) { tocChapters[li.getAttribute('data-toc-chapter')] = li; });
    var tocTopics = {};
    document.querySelectorAll('[data-toc-topic]').forEach(function (a) { tocTopics[a.getAttribute('data-toc-topic')] = a; });
    var topLink = document.getElementById('hc-top');
    var ticking = false;

    function spy() {
        ticking = false;
        var line = window.innerHeight * 0.3;
        var chapterId = null, topicId = null;
        chapters.forEach(function (c) { if (c.getBoundingClientRect().top <= line) chapterId = c.id; });
        topics.forEach(function (t) { if (t.getBoundingClientRect().top <= line) topicId = t.id; });
        Object.keys(tocChapters).forEach(function (id) { tocChapters[id].classList.toggle('is-active', id === chapterId); });
        Object.keys(tocTopics).forEach(function (id) { tocTopics[id].classList.toggle('is-active', id === topicId); });
        topLink.classList.toggle('is-visible', window.scrollY > 900);
    }
    window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(spy); } }, { passive: true });
    spy();
    topLink.addEventListener('click', function () { window.scrollTo({ top: 0 }); });

    /* ---------- Phone contents drawer ---------- */
    var toc = document.getElementById('hc-toc');
    var tocToggle = document.getElementById('hc-toc-toggle');
    function closeToc() { toc.classList.remove('is-open'); tocToggle.setAttribute('aria-expanded', 'false'); }
    tocToggle.addEventListener('click', function () {
        var open = toc.classList.toggle('is-open');
        tocToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    toc.addEventListener('click', function (e) { if (e.target.closest('a')) closeToc(); });

    /* ---------- Opening a deep link to an FAQ item ---------- */
    if (location.hash) {
        var target = document.getElementById(location.hash.slice(1));
        if (target && target.tagName === 'DETAILS') target.open = true;
    }

    /* ---------- Printing (Ctrl+P) and the PDF export ----------
       Every FAQ answer is opened for print. The exported PDF is built from
       this same print stylesheet by `php artisan help:export-pdf`, which
       loads the page from a file: URL — answers start open there. */
    var wasOpen = [];
    window.addEventListener('beforeprint', function () {
        wasOpen = faqs.map(function (d) { return d.open; });
        faqs.forEach(function (d) { d.open = true; });
    });
    window.addEventListener('afterprint', function () {
        faqs.forEach(function (d, i) { d.open = wasOpen[i] || false; });
    });
    if (location.protocol === 'file:') faqs.forEach(function (d) { d.open = true; });
})();
</script>
